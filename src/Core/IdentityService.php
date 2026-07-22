<?php

namespace App\Core;

/**
 * Dummy service to simulate fetching a logged-in user's ID.
 */
class IdentityService {
    public static function getUserId(): ?int { 
        // In a real application, this would read from the session or JWT token.
        return 1; // Simulating a successful lookup for User ID 1
    }

    /**
     * Dummy service to simulate getting authorized user roles/permissions.
     */
    public static function getUserRoles(): array {
        return ['user'];
    }
}

