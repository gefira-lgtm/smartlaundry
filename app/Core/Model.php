<?php
namespace App\Core;

use PDO;

abstract class Model {
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getTable(): string {
        return $this->table;
    }

    public function getPrimaryKey(): string {
        return $this->primaryKey;
    }

    /**
     * Get all records
     */
    public function all(string $orderBy = 'id DESC'): array {
        $stmt = $this->db->query("SELECT * FROM `{$this->table}` ORDER BY {$orderBy}");
        return $stmt->fetchAll();
    }

    /**
     * Find record by primary key
     */
    public function find($id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find by column and value
     */
    public function findBy(string $column, $value): ?array {
        return $this->first([$column => $value]);
    }

    /**
     * Find by specific column/conditions
     */
    public function where(array $conditions, string $orderBy = 'id DESC', ?int $limit = null): array {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                $cleanCol = str_replace('`', '', $col);
                $paramKey = str_replace('.', '_', $cleanCol);
                $clauses[] = "`{$cleanCol}` = :{$paramKey}";
                $params[$paramKey] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        $sql .= " ORDER BY {$orderBy}";
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find first matching record
     */
    public function first(array $conditions): ?array {
        $results = $this->where($conditions, 'id ASC', 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Insert a new record and return last inserted ID
     */
    public function create(array $data): string {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $fields = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $this->db->lastInsertId();
    }

    public function insertGetId(array $data): string {
        return $this->create($data);
    }

    /**
     * Update an existing record
     */
    public function update($id, array $data): bool {
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

        $fields = [];
        $params = [];
        foreach ($data as $col => $val) {
            $fields[] = "`{$col}` = :{$col}";
            $params[$col] = $val;
        }
        $fieldsStr = implode(', ', $fields);

        $sql = "UPDATE `{$this->table}` SET {$fieldsStr} WHERE `{$this->primaryKey}` = :_id";
        $params['_id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a record
     */
    public function delete($id): bool {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Execute raw prepared query (SELECT)
     */
    public function query(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute raw query and fetch first record
     */
    public function firstQuery(string $sql, array $params = []): ?array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute raw query (INSERT/UPDATE/DELETE) and return affected rows
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int {
        $sql = "SELECT COUNT(*) as total FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                $cleanCol = str_replace('`', '', $col);
                $paramKey = str_replace('.', '_', $cleanCol);
                $clauses[] = "`{$cleanCol}` = :{$paramKey}";
                $params[$paramKey] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Pagination Helper
     */
    public function paginate(int $page = 1, int $perPage = 10, array $conditions = [], string $orderBy = 'id DESC'): array {
        $page = max(1, $page);
        $total = $this->count($conditions);
        $totalPages = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $col => $val) {
                $cleanCol = str_replace('`', '', $col);
                $paramKey = str_replace('.', '_', $cleanCol);
                $clauses[] = "`{$cleanCol}` = :{$paramKey}";
                $params[$paramKey] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        $sql .= " ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }

    /**
     * Transaction helpers
     */
    public function beginTransaction(): bool {
        return $this->db->beginTransaction();
    }

    public function commit(): bool {
        return $this->db->commit();
    }

    public function rollBack(): bool {
        return $this->db->rollBack();
    }
}
