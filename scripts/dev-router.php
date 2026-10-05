<?php

declare(strict_types=1);

// PHP's built-in server needs a router so application routes and public assets
// can coexist during local development. Existing files are served directly;
// everything else is sent through the front controller.
$publicRoot = realpath(dirname(__DIR__) . '/public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = realpath($publicRoot . $requestPath);

if (
    $publicRoot !== false
    && $requestedFile !== false
    && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

require $publicRoot . '/index.php';
