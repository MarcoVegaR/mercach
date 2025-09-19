<?php

// Router script for PHP built-in server to serve Laravel app.
// For requests that match a physical file in public/, let the server handle them.
// Otherwise, forward the request to public/index.php (Laravel front controller).

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
