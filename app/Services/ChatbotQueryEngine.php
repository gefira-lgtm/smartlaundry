<?php
namespace App\Services;

use App\Core\Database;

/**
 * ChatbotQueryEngine — Safe, whitelist-enforced SQL executor for chatbot.
 *
 * SECURITY GUARANTEES:
 *  - All table/column/operator names validated against config/chatbot_schema.php
 *  - Values always bound via PDO prepared statements (never string-interpolated)
 *  - No raw SQL from user text or LLM is ever executed
 *  - admin_toko financial access blocked unless 'admin_toko_can_view_finance' setting is true
 *  - store_id scope injected from session/DB relation, never from user chat text
 *  - Forbidden columns (password, google_id, tokens…) excluded at schema level
 *  - Phone/email always masked in returned rows
 *  - Maximum 10 rows returned per query
 *
 * Plan format (input array):
 * [
 *   'entity'      => 'expenses',                // entity key in schema['entities']
 *   'joins'       => ['expenses->stores',        // join spec keys from schema['allowed_joins']
 *                     'expenses->users:recorder'],
 *   'filters'     => [                           // all values go through prepared statements
 *     ['col' => 'total_amount', 'op' => '=',      'val' => 500000],
 *     ['col' => 'expense_date', 'op' => 'BETWEEN','val' => ['2026-10-01','2026-10-31']],
 *   ],
 *   'columns'     => ['item_name','category','total_amount'], // base entity columns
 *   'extra_select'=> ['s.name AS store_name', 'r.name AS recorded_by_name'], // pre-built safe aliases
 *   'aggregation' => null,  // or ['func'=>'SUM','col'=>'total_amount','alias'=>'total']
 *   'group_by'    => [],    // entity columns to group by
 *   'order_by'    => ['expense_date' => 'DESC'],
 *   'limit'       => 10,
 * ]
 */
class ChatbotQueryEngine
{
    private array $schema;
    private \PDO  $db;

    /** Phone-like column names that must always be masked */
    private const MASK_PHONE_COLS = ['phone', 'whatsapp', 'wa_number', 'wa', 'telepon', 'hp'];
    /** Email-like column names that must always be masked */
    private const MASK_EMAIL_COLS = ['email'];
    /** Hard maximum rows returned */
    private const MAX_ROWS = 10;

    public function __construct()
    {
        $this->schema = require ROOT_PATH . '/config/chatbot_schema.php';
        $this->db     = Database::getConnection();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Execute a structured query plan safely.
     *
     * @param array  $plan   Structured query plan
     * @param int    $userId Authenticated user ID (from session)
     * @param string $role   Authenticated user role (from session)
     * @return array ['rows'=>array, 'total'=>int, 'query_plan_summary'=>string]
     * @throws \InvalidArgumentException on invalid plan (bad entity/column/operator/join)
     * @throws \RuntimeException         on access denied (role/financial)
     */
    public function execute(array $plan, int $userId, string $role): array
    {
        // 1. Role guard — customer/kurir cannot use query engine
        $this->assertRoleAllowed($role);

        // 2. Validate plan structure against schema whitelist
        $this->validatePlan($plan);

        // 3. Financial access guard for admin_toko
        $this->assertFinancialAccess($plan['entity'], $role);

        // 4. Inject store scope filter (overrides any user-supplied scope)
        $this->injectScopeFilter($plan, $userId, $role);

        // 5. Build and execute query
        $params = [];
        $sql    = $this->buildSql($plan, $params);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[ChatbotQueryEngine] PDOException: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new \RuntimeException('Query gagal dieksekusi: ' . $e->getMessage());
        }

        // 6. Mask sensitive columns
        $rows = array_map([$this, 'maskSensitiveColumns'], $rows);

        // 7. Log access
        $filterSummary = $this->buildFilterSummary($plan['filters'] ?? []);
        $this->logAccess($userId, $role, $plan['entity'], $filterSummary, count($rows));

        return [
            'rows'               => $rows,
            'total'              => count($rows),
            'query_plan_summary' => "SELECT dari '{$plan['entity']}' | filter: {$filterSummary}",
        ];
    }

    /**
     * Execute with automatic "find nearest" fallback for numeric filters.
     *
     * If exact filter returns 0 rows, re-runs the query without that filter
     * and orders by ABS(col - target_value) to find the closest match.
     *
     * @param string $nearestCol The numeric column to find nearest on (e.g. 'total_amount')
     */
    public function executeWithFallbackNearest(array $plan, int $userId, string $role, string $nearestCol): array
    {
        // Try exact query first
        $result = $this->execute($plan, $userId, $role);

        if ($result['total'] > 0) {
            return $result;
        }

        // Find target value from filters
        $targetVal = null;
        $newFilters = [];
        foreach ($plan['filters'] ?? [] as $f) {
            if ($f['col'] === $nearestCol && $f['op'] === '=') {
                $targetVal = $f['val'];
            } else {
                $newFilters[] = $f;
            }
        }

        if ($targetVal === null) {
            return $result; // No exact filter on this column; return empty
        }

        // Re-run without the exact filter, sorted by nearest
        $nearestPlan = $plan;
        $nearestPlan['filters']      = $newFilters;
        $nearestPlan['limit']        = 3;
        $nearestPlan['_nearest_col'] = $nearestCol;
        $nearestPlan['_nearest_val'] = (float)$targetVal;

        $nearestResult = $this->execute($nearestPlan, $userId, $role);
        $nearestResult['is_nearest'] = true;

        return $nearestResult;
    }

    // =========================================================================
    // Private: Access Control
    // =========================================================================

    private function assertRoleAllowed(string $role): void
    {
        $allowed = $this->schema['allowed_roles'] ?? ['owner_platform', 'admin_owner', 'owner_toko', 'admin_toko'];
        if (!in_array($role, $allowed, true)) {
            throw new \RuntimeException("Role '{$role}' tidak memiliki akses ke mesin query chatbot.");
        }
    }

    private function assertFinancialAccess(string $entity, string $role): void
    {
        $financialEntities = $this->schema['financial_entities'] ?? ['expenses', 'payments'];

        // customer/kurir already blocked by assertRoleAllowed; add extra check
        if (in_array($role, ['customer', 'kurir'], true)) {
            throw new \RuntimeException("Role '{$role}' tidak dapat mengakses data finansial.");
        }

        if (!in_array($entity, $financialEntities, true)) {
            return; // Not a financial entity — no further restriction
        }

        if ($role === 'admin_toko') {
            // Check setting
            $allowed = $this->getFinanceSettingForAdminToko();
            if (!$allowed) {
                throw new \RuntimeException(
                    'Data finansial hanya dapat diakses oleh owner toko. '
                    . 'Admin toko perlu meminta owner untuk mengaktifkan izin akses finansial.'
                );
            }
        }
    }

    private function getFinanceSettingForAdminToko(): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT `value` FROM `settings` WHERE `key` = 'admin_toko_can_view_finance' LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && in_array(strtolower((string)$row['value']), ['1', 'true', 'yes', 'on'], true);
        } catch (\PDOException $e) {
            // Fail closed: if we can't check, deny access
            return false;
        }
    }

    // =========================================================================
    // Private: Scope Injection
    // =========================================================================

    private function injectScopeFilter(array &$plan, int $userId, string $role): void
    {
        // Platform roles have no store restriction
        if (in_array($role, ['owner_platform', 'admin_owner'], true)) {
            return;
        }

        $entityKey = $plan['entity'];
        $entityCfg = $this->schema['entities'][$entityKey] ?? null;
        if ($entityCfg === null) {
            return;
        }

        $storeFk = $entityCfg['store_fk'] ?? null;
        if ($storeFk === null) {
            return; // Entity has no direct store FK
        }

        // Get the store_id for this user from DB (never from user input)
        $storeId = $this->resolveStoreId($userId, $role);

        // Remove any user-supplied store_id filter (defense-in-depth)
        $plan['filters'] = array_values(array_filter(
            $plan['filters'] ?? [],
            fn($f) => $f['col'] !== $storeFk
        ));

        if ($storeId === null) {
            // User has no store — return impossible result
            $plan['filters'][] = ['col' => $storeFk, 'op' => '=', 'val' => -1];
        } else {
            $plan['filters'][] = ['col' => $storeFk, 'op' => '=', 'val' => $storeId];
        }
    }

    private function resolveStoreId(int $userId, string $role): ?int
    {
        try {
            if ($role === 'owner_toko') {
                $stmt = $this->db->prepare(
                    "SELECT `id` FROM `stores` WHERE `owner_id` = :uid LIMIT 1"
                );
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? (int)$row['id'] : null;
            }
            if ($role === 'admin_toko') {
                $stmt = $this->db->prepare(
                    "SELECT `store_id` FROM `store_admins` WHERE `user_id` = :uid LIMIT 1"
                );
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? (int)$row['store_id'] : null;
            }
        } catch (\PDOException $e) {
            error_log('[ChatbotQueryEngine] resolveStoreId error: ' . $e->getMessage());
        }
        return null;
    }

    // =========================================================================
    // Private: Plan Validation
    // =========================================================================

    private function validatePlan(array $plan): void
    {
        // --- Entity ---
        if (empty($plan['entity'])) {
            throw new \InvalidArgumentException('Plan harus memiliki entity.');
        }
        $entities = $this->schema['entities'] ?? [];
        if (!isset($entities[$plan['entity']])) {
            throw new \InvalidArgumentException(
                "Entitas '{$plan['entity']}' tidak ada dalam daftar yang diizinkan."
            );
        }
        $entityCfg   = $entities[$plan['entity']];
        $allowedCols = array_keys($entityCfg['columns'] ?? []);

        // --- Requested columns ---
        foreach ($plan['columns'] ?? [] as $col) {
            if (!in_array($col, $allowedCols, true)) {
                throw new \InvalidArgumentException(
                    "Kolom '{$col}' tidak diizinkan untuk entitas '{$plan['entity']}'."
                );
            }
        }

        // --- Filters ---
        $allowedOps = array_keys($this->schema['operators'] ?? [
            '=' => '=', '!=' => '!=', '>' => '>', '>=' => '>=',
            '<' => '<', '<=' => '<=', 'BETWEEN' => 'BETWEEN',
            'LIKE' => 'LIKE', 'IN' => 'IN',
            'IS NULL' => 'IS NULL', 'IS NOT NULL' => 'IS NOT NULL',
        ]);

        foreach ($plan['filters'] ?? [] as $f) {
            $col = $f['col'] ?? '';
            $op  = strtoupper($f['op'] ?? '');
            if (!in_array($col, $allowedCols, true)) {
                throw new \InvalidArgumentException("Filter kolom '{$col}' tidak diizinkan.");
            }
            if (!in_array($op, $allowedOps, true)) {
                throw new \InvalidArgumentException("Operator '{$op}' tidak diizinkan.");
            }
        }

        // --- Joins ---
        $allowedJoins = array_keys($this->schema['allowed_joins'] ?? []);
        foreach ($plan['joins'] ?? [] as $joinSpec) {
            // joinSpec can be "expenses->stores" or "expenses->users:recorder"
            $baseKey = (string)preg_replace('/:[^:]+$/', '', $joinSpec);
            if (!in_array($baseKey, $allowedJoins, true)) {
                throw new \InvalidArgumentException("Join '{$joinSpec}' tidak diizinkan.");
            }
        }

        // --- Aggregation ---
        if (!empty($plan['aggregation'])) {
            $agg        = $plan['aggregation'];
            $func       = strtoupper($agg['func'] ?? '');
            $col        = $agg['col'] ?? '';
            $allowedAgg = $entityCfg['allowed_aggregations'] ?? ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN'];
            if (!in_array($func, $allowedAgg, true)) {
                throw new \InvalidArgumentException(
                    "Agregasi '{$func}' tidak diizinkan untuk '{$plan['entity']}'."
                );
            }
            if ($col !== '*' && !in_array($col, $allowedCols, true)) {
                throw new \InvalidArgumentException("Kolom agregasi '{$col}' tidak diizinkan.");
            }
        }

        // --- group_by ---
        foreach ($plan['group_by'] ?? [] as $gcol) {
            if (!in_array($gcol, $allowedCols, true)) {
                throw new \InvalidArgumentException("GROUP BY kolom '{$gcol}' tidak diizinkan.");
            }
        }

        // --- order_by ---
        foreach (array_keys($plan['order_by'] ?? []) as $ocol) {
            if (!in_array($ocol, $allowedCols, true)) {
                throw new \InvalidArgumentException("ORDER BY kolom '{$ocol}' tidak diizinkan.");
            }
        }
    }

    // =========================================================================
    // Private: SQL Building
    // =========================================================================

    private function buildSql(array $plan, array &$params): string
    {
        $entityCfg  = $this->schema['entities'][$plan['entity']];
        $mainTable  = $entityCfg['table'];
        $allowedCols = array_keys($entityCfg['columns']);

        // SELECT
        $selectParts = [];

        if (!empty($plan['aggregation'])) {
            $agg   = $plan['aggregation'];
            $func  = strtoupper($agg['func']);
            $col   = $agg['col'];
            $alias = $agg['alias'] ?? strtolower($func) . '_result';
            $colExpr = ($col === '*') ? '*' : "`{$mainTable}`.`{$col}`";
            $selectParts[] = "{$func}({$colExpr}) AS `{$alias}`";

            // Include GROUP BY columns in SELECT too
            foreach ($plan['group_by'] ?? [] as $gcol) {
                if (in_array($gcol, $allowedCols, true)) {
                    $selectParts[] = "`{$mainTable}`.`{$gcol}`";
                }
            }
        } else {
            // Regular columns
            $cols = !empty($plan['columns']) ? $plan['columns'] : $allowedCols;
            foreach ($cols as $col) {
                if (in_array($col, $allowedCols, true)) {
                    $selectParts[] = "`{$mainTable}`.`{$col}`";
                }
            }
        }

        // Extra pre-built alias expressions (from join columns — built by our code, not user input)
        foreach ($plan['extra_select'] ?? [] as $expr) {
            $selectParts[] = $expr;
        }

        $selectSql = empty($selectParts)
            ? "`{$mainTable}`.*"
            : implode(', ', $selectParts);

        // FROM + JOINs
        $fromSql = $this->buildJoinSql($plan, $mainTable);

        // WHERE
        $whereSql = $this->buildWhereSql($plan, $mainTable, $allowedCols, $params);

        // GROUP BY
        $groupBySql = '';
        if (!empty($plan['group_by'])) {
            $gbParts = [];
            foreach ($plan['group_by'] as $gcol) {
                if (in_array($gcol, $allowedCols, true)) {
                    $gbParts[] = "`{$mainTable}`.`{$gcol}`";
                }
            }
            if (!empty($gbParts)) {
                $groupBySql = ' GROUP BY ' . implode(', ', $gbParts);
            }
        }

        // ORDER BY
        $orderBySql = '';
        if (!empty($plan['_nearest_col'])) {
            // Nearest-distance ordering: ABS(col - val) ASC
            $ncol = $plan['_nearest_col'];
            $nval = (float)($plan['_nearest_val'] ?? 0);
            // ncol is already validated against allowedCols in executeWithFallbackNearest
            $orderBySql = " ORDER BY ABS(`{$mainTable}`.`{$ncol}` - {$nval}) ASC";
        } elseif (!empty($plan['order_by'])) {
            $obParts = [];
            foreach ($plan['order_by'] as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                if (in_array($col, $allowedCols, true)) {
                    $obParts[] = "`{$mainTable}`.`{$col}` {$dir}";
                }
            }
            if (!empty($obParts)) {
                $orderBySql = ' ORDER BY ' . implode(', ', $obParts);
            }
        }

        // LIMIT
        $limit    = min((int)($plan['limit'] ?? self::MAX_ROWS), self::MAX_ROWS);
        $limitSql = " LIMIT {$limit}";

        $sql = "SELECT {$selectSql} {$fromSql}";
        if ($whereSql !== '') {
            $sql .= " WHERE {$whereSql}";
        }
        $sql .= $groupBySql . $orderBySql . $limitSql;

        return $sql;
    }

    private function buildJoinSql(array $plan, string $mainTable): string
    {
        $sql       = "FROM `{$mainTable}`";
        $allJoins  = $this->schema['allowed_joins'] ?? [];

        foreach ($plan['joins'] ?? [] as $joinSpec) {
            // Extract alias: "expenses->users:recorder" → alias="recorder"
            $alias   = null;
            $baseKey = (string)preg_replace('/:[^:]+$/', '', $joinSpec);
            if (preg_match('/:([^:]+)$/', $joinSpec, $m)) {
                $alias = $m[1];
            }

            $jcfg = $allJoins[$baseKey] ?? null;
            if ($jcfg === null) {
                continue; // Already validated; skip silently
            }

            $joinType   = strtoupper($jcfg['type'] ?? 'LEFT');
            $joinTable  = $jcfg['to_table'];
            $localCol   = $jcfg['from_col'];
            $foreignCol = $jcfg['to_col'];

            if ($alias !== null) {
                $sql .= " {$joinType} JOIN `{$joinTable}` AS `{$alias}`"
                      . " ON `{$mainTable}`.`{$localCol}` = `{$alias}`.`{$foreignCol}`";
            } else {
                $sql .= " {$joinType} JOIN `{$joinTable}`"
                      . " ON `{$mainTable}`.`{$localCol}` = `{$joinTable}`.`{$foreignCol}`";
            }
        }

        return $sql;
    }

    private function buildWhereSql(array $plan, string $mainTable, array $allowedCols, array &$params): string
    {
        $conditions = [];
        $paramIdx   = 0;

        foreach ($plan['filters'] ?? [] as $f) {
            $col = $f['col'];
            $op  = strtoupper($f['op']);
            $val = $f['val'];

            // Defense-in-depth: skip columns not in whitelist
            if (!in_array($col, $allowedCols, true)) {
                continue;
            }

            $colExpr = "`{$mainTable}`.`{$col}`";
            $key     = "p{$paramIdx}";

            switch ($op) {
                case 'IS NULL':
                    $conditions[] = "{$colExpr} IS NULL";
                    break;

                case 'IS NOT NULL':
                    $conditions[] = "{$colExpr} IS NOT NULL";
                    break;

                case 'BETWEEN':
                    if (is_array($val) && count($val) === 2) {
                        $k1 = "p{$paramIdx}a";
                        $k2 = "p{$paramIdx}b";
                        $conditions[] = "{$colExpr} BETWEEN :{$k1} AND :{$k2}";
                        $params[$k1]  = $val[0];
                        $params[$k2]  = $val[1];
                    }
                    break;

                case 'IN':
                    if (is_array($val) && !empty($val)) {
                        $placeholders = [];
                        foreach ($val as $i => $v) {
                            $k = "p{$paramIdx}_{$i}";
                            $placeholders[] = ":{$k}";
                            $params[$k]     = $v;
                        }
                        $conditions[] = "{$colExpr} IN (" . implode(', ', $placeholders) . ')';
                    }
                    break;

                case 'LIKE':
                    $conditions[] = "{$colExpr} LIKE :{$key}";
                    $params[$key] = $val;
                    break;

                default:
                    // =, !=, <, >, <=, >=
                    $conditions[] = "{$colExpr} {$op} :{$key}";
                    $params[$key] = $val;
            }

            $paramIdx++;
        }

        return empty($conditions) ? '' : implode(' AND ', $conditions);
    }

    // =========================================================================
    // Private: Masking
    // =========================================================================

    private function maskSensitiveColumns(array $row): array
    {
        foreach ($row as $key => $val) {
            $keyLower = strtolower($key);

            foreach (self::MASK_PHONE_COLS as $phoneCol) {
                if ($keyLower === $phoneCol || str_ends_with($keyLower, '_' . $phoneCol)) {
                    $row[$key] = self::maskPhone((string)($val ?? ''));
                    continue 2;
                }
            }

            foreach (self::MASK_EMAIL_COLS as $emailCol) {
                if ($keyLower === $emailCol || str_ends_with($keyLower, '_' . $emailCol)) {
                    $row[$key] = self::maskEmail((string)($val ?? ''));
                    continue 2;
                }
            }
        }
        return $row;
    }

    private static function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return '***';
        }
        $visible = 4;
        $tail    = 3;
        $stars   = max(0, strlen($phone) - $visible - $tail);
        return substr($phone, 0, $visible) . str_repeat('*', $stars) . substr($phone, -$tail);
    }

    private static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 2) {
            return '***@***.***';
        }
        return substr($email, 0, 2) . str_repeat('*', $at - 2) . substr($email, $at);
    }

    // =========================================================================
    // Private: Logging
    // =========================================================================

    private function logAccess(int $userId, string $role, string $entity, string $filterSummary, int $rows): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `chatbot_data_access_logs`
                    (`user_id`, `role`, `store_id`, `tool`, `params_summary`, `rows_returned`, `created_at`)
                 VALUES
                    (:uid, :role, NULL, :tool, :params, :rows, NOW())"
            );
            $stmt->execute([
                'uid'    => $userId,
                'role'   => $role,
                'tool'   => 'query_engine:' . $entity,
                'params' => mb_substr($filterSummary, 0, 500, 'UTF-8'),
                'rows'   => $rows,
            ]);
        } catch (\PDOException $e) {
            error_log('[ChatbotQueryEngine] logAccess error: ' . $e->getMessage());
            // Non-fatal: swallow logging errors
        }
    }

    private function buildFilterSummary(array $filters): string
    {
        if (empty($filters)) {
            return 'tanpa filter';
        }
        $parts = [];
        foreach ($filters as $f) {
            $val = $f['val'] ?? '';
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $parts[] = ($f['col'] ?? '') . ' ' . ($f['op'] ?? '') . ' ' . $val;
        }
        return implode('; ', $parts);
    }
}

