<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    $sessionName = $config['app']['session_name'] ?? 'cms_session';
    session_name($sessionName);
    session_start();
}
