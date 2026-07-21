<?php

namespace VibeBoard\Core;

/**
 * Converts PHP errors, warnings and uncaught exceptions into JSON responses.
 */
class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'PHP error: ' . $message,
            'file' => $file,
            'line' => $line,
        ]);
        exit;
    }

    public static function handleException(\Throwable $e): void
    {
        $code = $e instanceof \InvalidArgumentException ? 400 : 500;
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $e->getMessage(),
        ]);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Fatal error: ' . $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        }
    }
}
