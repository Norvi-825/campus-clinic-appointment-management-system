<?php
/**
 * Database Connection & Application Configuration (PHP PDO)
 *
 * Loads configuration from config/database.local.php if present,
 * otherwise falls back to environment variables or XAMPP defaults.
 */

declare(strict_types=1);

if (!function_exists('ccams_config')) {
    /**
     * Returns the merged CCAMS configuration array.
     */
    function ccams_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $defaults = require __DIR__ . '/database.example.php';
        $localFile = __DIR__ . '/database.local.php';

        if (is_file($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                $defaults = array_replace_recursive($defaults, $local);
            }
        }

        // Auto-detect XAMPP subfolder (e.g., /ccams) when base_path is empty
        if ($defaults['app']['base_path'] === '' && isset($_SERVER['SCRIPT_NAME'])) {
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            // Check known subdirectories to compute root web path
            foreach (['/student/', '/doctor/', '/admin/'] as $subDir) {
                $pos = strpos($scriptName, $subDir);
                if ($pos !== false) {
                    $defaults['app']['base_path'] = substr($scriptName, 0, $pos);
                    break;
                }
            }
            if ($defaults['app']['base_path'] === '') {
                $dir = rtrim(dirname($scriptName), '/');
                $defaults['app']['base_path'] = ($dir === '.' ? '' : $dir);
            }
        }

        $config = $defaults;
        return $config;
    }
}

if (!function_exists('get_pdo')) {
    /**
     * Returns a singleton PDO connection to the MySQL/MariaDB database.
     */
    function get_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $cfg = ccams_config()['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        } catch (PDOException $e) {
            error_log('CCAMS Database Connection Error: ' . $e->getMessage());
            http_response_code(500);
            exit('Unable to connect to the campus clinic database. Please check server configuration or try again later.');
        }

        return $pdo;
    }
}
