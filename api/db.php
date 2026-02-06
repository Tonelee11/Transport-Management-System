<?php
// api/db.php - Database connection helper

function env($key, $default = null)
{
    $path = __DIR__ . '/.env';
    if (!file_exists($path))
        return $default;
    $contents = file_get_contents($path);
    $lines = explode("\n", $contents);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0)
            continue;
        list($k, $v) = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim($v);
        }
    }
    return $default;
}

function getDB()
{
    static $pdo = null;
    if ($pdo !== null)
        return $pdo;

    // Use getenv() for Render environment variables
    $hostRaw = getenv('DB_HOST') ?: env('DB_HOST', 'db');
    $dbname = getenv('DB_NAME') ?: env('DB_NAME', 'logistics');
    $user = getenv('DB_USER') ?: env('DB_USER', 'root');
    $pass = getenv('DB_PASS') ?: env('DB_PASS', 'tw_pass');
    $port = getenv('DB_PORT') ?: env('DB_PORT', '4000');

    // Clean Host: Remove port if user accidentally included it in the host string
    $host = explode(':', $hostRaw)[0];

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // TiDB Cloud REQUIRES SSL. 
        if ($host !== 'db' && $host !== '127.0.0.1' && $host !== 'localhost') {
            // TiDB Serverless requires SSL. We specify the CA bundle path which exists in the container.
            $caPath = '/etc/ssl/certs/ca-certificates.crt';

            if (file_exists($caPath)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            } else {
                // Fallback for environments without the standard CA path
                $options[1015] = 1; // PDO::MYSQL_OPT_SSL_MODE = 1 (REQUIRED)
            }
        }

        $pdo = new PDO($dsn, $user, $pass, $options);

        // Synchronize PHP and DB timezones (East Africa Time)
        $pdo->exec("SET time_zone = '+03:00';");

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        // Detail the error including the host we tried to connect to
        echo json_encode([
            'error' => 'Database connection failed',
            'debug' => [
                'message' => $e->getMessage(),
                'tried_host' => $host,
                'tried_port' => $port
            ]
        ]);
        exit;
    }
}

/*
What this file does
- Reads database credentials from `.env` file
- Creates a PDO database connection
- Returns a reusable database connection
- Handles connection errors gracefully
*/