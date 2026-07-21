<?php

/**
 * VibeBoard Global Exception Handler & Logger
 * 
 * Provides a consistent way to handle database connectivity, 
 * route failures, and general exceptions with user-friendly messages.
 */

declare(strict_types=1);

namespace VibeBoard\Core;

use Throwable;
use PDOException;

class ErrorHandler {
    /**
     * Handle an exception gracefully.
     * Logs the technical details internally but returns a clean message for the UI.
     */
    public static function handle(Throwable $e, string $context = 'General'): void {
        // Log detail internally (log file or system logger)
        error_log("[VibeBoard Error] Context: $context | Message: " . $e->getMessage());

        if ($e instanceof PDOException) {
            // Specific handling for DB issues
            http_response_code(500);
            echo "System Error: We are currently experiencing difficulties connecting to the database. Please try again shortly.";
        } else {
            // General route/logic failures
            http_response_code(404);
            echo "Application Error: The requested resource could not be resolved correctly.";
        }
    }

    /**
     * Specific check for connection drops during the lifecycle of a request.
     */
    public static function handleConnectionLoss(): void {
        error_log("[VibeBoard Alert] Database connection dropped.");
        http_response_code(503);
        echo "Service Unavailable: Database connection lost. Our team has been notified.";
    }
}

/**
 * Note: Exception/error handler registration is managed by the front controller
 * (public/index.php). This file contains only the ErrorHandler class definition.
 */
