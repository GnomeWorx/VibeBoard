<?php

/**
 * VibeBoard Base Controller
 * 
 * Provides basic structure for controllers.
 */

declare(strict_types=1);

namespace VibeBoard\Controllers;

abstract class Controller {
    protected function render(string $view, array $data = []): void {
        // Placeholder for a rendering engine (like Twig) integration
        echo "Rendering view: " . $view;
    }
}
