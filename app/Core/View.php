<?php
namespace App\Core;

class View {
    /**
     * Render a view file with optional layout
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void {
        // Extract data into local variables
        extract($data);

        $viewFile = VIEW_PATH . '/' . ltrim($view, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$viewFile}");
        }

        // Start output buffering for view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = VIEW_PATH . '/' . ltrim($layout, '/') . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        // If no layout, print content directly
        echo $content;
    }
}
