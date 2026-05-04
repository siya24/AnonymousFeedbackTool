<?php declare(strict_types=1);

namespace App\Core;

class Authorization {
    public const ROLE_HR      = 'hr';
    public const ROLE_ETHICS  = 'ethics';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_OFFICER = 'officer';

    /** Roles that may access the HR console at all */
    public const CONSOLE_ROLES = [self::ROLE_HR, self::ROLE_ETHICS, self::ROLE_MANAGER, self::ROLE_OFFICER];

    /** Roles permitted to create/update cases */
    public const CASE_WRITE_ROLES = [self::ROLE_HR, self::ROLE_ETHICS, self::ROLE_OFFICER];

    /** Roles permitted to manage system configuration (categories, statuses, stages) */
    public const CONFIG_ROLES = [self::ROLE_HR, self::ROLE_OFFICER];
    
    private ?array $user = null;

    public function __construct(private JwtService $jwt) {}

    
    public function authenticate(): bool {
        $token = JwtService::getBearerToken();
        
        if (!$token) {
            return false;
        }

        $decoded = $this->jwt->decode($token);
        
        if (!$decoded) {
            return false;
        }

        $this->user = $decoded;
        return true;
    }

    
    public function isAuthenticated(): bool {
        return $this->user !== null;
    }

    
    public function getUser(): ?array {
        return $this->user;
    }

    
    public function getUserId(): ?string {
        return isset($this->user['user_id']) ? (string) $this->user['user_id'] : null;
    }

    
    public function getRole(): ?string {
        return $this->user['role'] ?? null;
    }

    
    public function hasRole(string $role): bool {
        return $this->isAuthenticated() && $this->getRole() === $role;
    }

    
    public function hasAnyRole(array $roles): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }
        return in_array($this->getRole(), $roles, true);
    }

    
    public function requireAuth(): void {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required', 401);
        }
    }

    
    public function requireRole(string $role): void {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            throw new \RuntimeException('Insufficient permissions', 403);
        }
    }

    
    public function requireAnyRole(array $roles): void {
        $this->requireAuth();
        
        if (!$this->hasAnyRole($roles)) {
            throw new \RuntimeException('Insufficient permissions', 403);
        }
    }
}
