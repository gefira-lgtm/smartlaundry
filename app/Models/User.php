<?php
namespace App\Models;

use App\Core\Model;

class User extends Model {
    protected string $table = 'users';
    protected string $primaryKey = 'id';

    /**
     * Find user by email including joined role info
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT u.*, r.name AS role_name, r.display_name AS role_display 
                FROM `{$this->table}` u
                JOIN `roles` r ON u.role_id = r.id
                WHERE u.email = :email
                LIMIT 1";
        return $this->firstQuery($sql, ['email' => $email]);
    }

    /**
     * Find user by phone number
     */
    public function findByPhone(string $phone): ?array {
        $sql = "SELECT * FROM `{$this->table}` WHERE phone = :phone LIMIT 1";
        return $this->firstQuery($sql, ['phone' => $phone]);
    }

    /**
     * Find user by Google OAuth ID
     */
    public function findByGoogleId(string $googleId): ?array {
        $sql = "SELECT u.*, r.name AS role_name, r.display_name AS role_display 
                FROM `{$this->table}` u
                JOIN `roles` r ON u.role_id = r.id
                WHERE u.google_id = :google_id
                LIMIT 1";
        return $this->firstQuery($sql, ['google_id' => $googleId]);
    }

    /**
     * Find user by ID with joined role info
     */
    public function findWithRole(int $id): ?array {
        $sql = "SELECT u.*, r.name AS role_name, r.display_name AS role_display 
                FROM `{$this->table}` u
                JOIN `roles` r ON u.role_id = r.id
                WHERE u.id = :id
                LIMIT 1";
        return $this->firstQuery($sql, ['id' => $id]);
    }

    /**
     * Verify plain password against hashed password
     */
    public function verifyPassword(string $password, string $hashedPassword): bool {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Get role slug name for a user
     */
    public function getRoleName(int $userId): ?string {
        $user = $this->findWithRole($userId);
        return $user['role_name'] ?? null;
    }

    /**
     * Create a new customer user account
     */
    public function createCustomer(array $data): string {
        $roleModel = new Role();
        $customerRole = $roleModel->findByName('customer');
        $roleId = $customerRole ? (int)$customerRole['id'] : 6;

        $hashedPassword = !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null;

        return $this->create([
            'role_id'           => $roleId,
            'name'              => $data['name'],
            'email'             => strtolower(trim($data['email'])),
            'password'          => $hashedPassword,
            'phone'             => $data['phone'] ?? null,
            'google_id'         => $data['google_id'] ?? null,
            'avatar'            => $data['avatar'] ?? 'avatar_default.png',
            'is_active'         => $data['is_active'] ?? 1,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'phone_verified_at' => $data['phone_verified_at'] ?? null,
        ]);
    }

    /**
     * Get store associated with a user (if store owner or store admin)
     */
    public function getStore(int $userId): ?array {
        // Check if owner
        $sqlOwner = "SELECT * FROM `stores` WHERE `owner_id` = :user_id LIMIT 1";
        $store = $this->firstQuery($sqlOwner, ['user_id' => $userId]);
        if ($store) {
            return $store;
        }

        // Check if store admin pivot
        $sqlAdmin = "SELECT s.* FROM `stores` s 
                     JOIN `store_admins` sa ON s.id = sa.store_id 
                     WHERE sa.user_id = :user_id 
                     LIMIT 1";
        return $this->firstQuery($sqlAdmin, ['user_id' => $userId]);
    }

    /**
     * Check if user profile is complete (Name, Phone, and at least 1 saved Address)
     */
    public function isProfileComplete(int $userId): array {
        $user = $this->find($userId);
        $missing = [];

        if (!$user || empty(trim($user['name']))) {
            $missing[] = 'Nama Lengkap';
        }

        if (!$user || empty(trim($user['phone'] ?? ''))) {
            $missing[] = 'Nomor WhatsApp / Telepon Aktif';
        }

        $addressModel = new Address();
        $addresses = $addressModel->findByUser($userId);
        if (empty($addresses)) {
            $missing[] = 'Alamat Penjemputan / Pengantaran (Minimal 1 Alamat)';
        }

        return [
            'is_complete' => empty($missing),
            'missing' => $missing,
            'address_count' => count($addresses),
            'user' => $user
        ];
    }

    /**
     * Update user profile information
     */
    public function updateProfile(int $userId, array $data): bool {
        $updateData = [
            'name' => trim($data['name']),
            'phone' => trim($data['phone'])
        ];

        if (!empty($data['avatar'])) {
            $updateData['avatar'] = $data['avatar'];
        }

        if (!empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        return (bool)$this->update($userId, $updateData);
    }
}

