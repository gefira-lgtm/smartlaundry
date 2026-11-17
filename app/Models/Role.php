<?php
namespace App\Models;

use App\Core\Model;

class Role extends Model {
    protected string $table = 'roles';
    protected string $primaryKey = 'id';

    /**
     * Find role by slug/name (e.g. 'owner_platform', 'customer')
     */
    public function findByName(string $name): ?array {
        return $this->first(['name' => $name]);
    }

    /**
     * Get all active roles ordered by ID
     */
    public function allRoles(): array {
        return $this->all('id ASC');
    }
}
