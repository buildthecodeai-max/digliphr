<?php

declare(strict_types=1);

/**
 * cPanel-friendly entry when document root is the project folder.
 * Prefer pointing the domain document root to /public instead.
 */
require __DIR__ . '/public/index.php';
