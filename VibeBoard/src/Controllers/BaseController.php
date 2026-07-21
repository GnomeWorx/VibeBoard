<?php

namespace VibeBoard\Controllers;

use VibeBoard\Config\Config;

/**
 * Base controller helpers for JSON responses and request parsing.
 */
abstract class BaseController
{
    protected function json(array $data, int $statusCode = 200): array
    {
        http_response_code($statusCode);
        return $data;
    }

    protected function ok(array $data = []): array
    {
        return $this->json(['success' => true] + $data);
    }

    protected function error(string $message, int $statusCode = 400): array
    {
        return $this->json(['error' => $message], $statusCode);
    }

    protected function input(): array
    {
        $body = file_get_contents('php://input');
        if ($body === '' || $body === false) {
            return [];
        }
        return json_decode($body, true) ?? [];
    }

    protected function validateStatus(?string $status): string
    {
        $allowed = ['Backlog', 'In Progress', 'QA-Review', 'Done'];
        if ($status === null || $status === '') {
            return 'Backlog';
        }
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status. Allowed: ' . implode(', ', $allowed));
        }
        return $status;
    }

    protected function appVersion(): string
    {
        return Config::app()['version'];
    }
}
