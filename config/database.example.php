<?php
/**
 * Example Database and Notification Sandbox Configuration
 *
 * Copy or configure local overrides in config/database.local.php (which is gitignored)
 * or set environment variables DB_HOST, DB_NAME, DB_USER, DB_PASS, SMS_SANDBOX_KEY.
 * Never commit real production credentials or API keys to GitHub.
 */

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'campus_clinic',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'CCAMS — USTED University Campus Clinic',
        'university' => 'USTED University',
        'base_path' => '', // e.g. '/ccams' if hosted in XAMPP htdocs/ccams
        'cancellation_cutoff_hours' => 2,
    ],
    'notifications' => [
        // Supported modes: 'mock', 'sms_sandbox'
        'default_external_mode' => 'mock',
        // Keep real sandbox credentials in environment variables or database.local.php
        'sms_sandbox_endpoint' => getenv('SMS_SANDBOX_ENDPOINT') ?: '',
        'sms_sandbox_api_key' => getenv('SMS_SANDBOX_API_KEY') ?: '',
        'sms_sender_id' => 'USTEDClinic',
    ],
];
