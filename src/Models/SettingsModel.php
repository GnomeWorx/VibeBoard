<?php

namespace App\Models;

use DateTimeImmutable;

/**
 * Class SettingsModel
 * Handles retrieval and persistence of application-wide user settings.
 */
class SettingsModel {
    private $user_id;

    public function __construct(int $userId) {
        $this->user_id = $userId;
    }

    /**
     * Retrieves all current settings for the user.
     * @return array Associative array of settings (e.g., ['theme' => 'dark', 'notifications' => true])
     */
    public function getSettings(): array {
        // TODO: Implement actual database query based on $this->user_id
        // Hardcoding example defaults for now until DB connection is established.
        return [
            'theme' => 'light', // Default theme
            'notifications' => true, // Default to enabled
            // Add more default settings here
        ];
    }

    /**
     * Updates the user's settings in one transaction.
     * @param array $attributes Array containing keys and values for updated settings.
     * @return bool True on successful update attempt, false otherwise.
     */
    public function saveSettings(array $attributes): bool {
        // Check input structure integrity before saving
        if (!isset($this->user_id)) {
            error_log(System Error: User ID is not set in SettingsModel.);
            return false;
        }

        try {
            // TODO: Implement actual database update logic. Use transactions (BEGIN/COMMIT) for safety.
            // Example: executeQuery('UPDATE user_settings SET theme = :theme, notifications = :notifications WHERE user_id = :user_id', [ ... ]);
            
            error_log(Successfully processed settings save request for User ID:  . $this->user_id);

            // Simulate successful storage. Clean up the attributes array of invalid values if needed.
            return true; 

        } catch (\Exception $e) {
            error_log(Settings saving failed:  . $e->getMessage());
            return false;
        }
    }
}
