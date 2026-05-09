<?php
// Usado apenas pelo servidor built-in do PHP (php -S)
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . '/public' . $path;
    if ($path !== '/' && file_exists($file) && !is_dir($file)) {
        return false; // serve arquivos estáticos normalmente
    }
}
require __DIR__ . '/public/index.php';
