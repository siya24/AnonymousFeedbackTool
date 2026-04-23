<?php declare(strict_types=1);

namespace App\Core;

class Authorization {
    public const ROLE_HR = 'hr';
    public const ROLE_ETHICS = 'ethics';
    
    private ?array $user = null;

    public function __construct(private JwtService $jwt) {}

    /**
     * Authenticate from JWT token in Authorization header
     */
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

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool {
        return $this->user !== null;
    }

    /**
     * Get current user data
     */
    public function getUser(): ?array {
        return $this->user;
    }

    /**
     * Get current user ID
     */
    public function getUserId(): ?int {
        return $this->user['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public function getRole(): ?string {
        return $this->user['role'] ?? null;
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool {
        return $this->isAuthenticated() && $this->getRole() === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool {
        if (!$this->isAuthenticated()) {
            return false;
        }
        return in_array($this->getRole(), $roles, true);
    }

    /**
     * Require authentication or throw exception
     */
    public function requireAuth(): void {
        if (!$this->isAuthenticated()) {
            throw new \RuntimeException('Authentication required', 401);
        }
    }

    /**
     * Require specific role or throw exception
     */
    public function requireRole(string $role): void {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            throw new \RuntimeException('Insufficient permissions', 403);
        }
    }

    /**
     * Require any of the given roles or throw exception
     */
    public function requireAnyRole(array $roles): void {
        $this->requireAuth();
        
        if (!$this->hasAnyRole($roles)) {
            throw new \RuntimeException('Insufficient permissions', 403);
        }
    }
}
