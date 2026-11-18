<?php
/**
 * PHPUnit Test Bootstrap
 */

declare(strict_types=1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/AppointmentService.php';
