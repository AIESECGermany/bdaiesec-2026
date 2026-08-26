<?php
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value !== false && $value !== null && $value !== '') {
        return $value;
    }

    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$name, $rawValue] = array_pad(explode('=', $line, 2), 2, '');
            if (trim($name) === $key) {
                $value = trim($rawValue, " \t\n\r\0\x0B\"");
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    return $default;
}

function envBool(string $key, bool $default = false): bool
{
    $value = env($key, $default ? 'true' : 'false');
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'y'], true);
}

function loggingEnabled(): bool
{
    $appEnv = strtolower((string) env('APP_ENV', ''));
    return $appEnv === 'test' || envBool('LOG_TO_FILE', false);
}

function appLogPath(): string
{
    $configuredPath = trim((string) env('LOG_PATH', ''));
    if ($configuredPath !== '') {
        return $configuredPath[0] === '/' ? $configuredPath : dirname(__DIR__) . '/' . $configuredPath;
    }

    return dirname(__DIR__) . '/logs/app.log';
}

function writeAppLog(string $message, array $context = []): void
{
    if (!loggingEnabled()) {
        return;
    }

    $logPath = appLogPath();
    $directory = dirname($logPath);
    if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
        return;
    }

    $entry = [
        'time' => date('c'),
        'message' => $message,
        'context' => $context,
    ];

    @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function dbDsn(): string
{
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $dbname = env('DB_NAME', 'aiesec_leads');
    $charset = 'utf8mb4';

    return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
}

function dbUser(): string
{
    return env('DB_USER', 'root');
}

function dbPassword(): string
{
    return env('DB_PASSWORD', '');
}

function smtpConfig(): array
{
    return [
        'host' => env('SMTP_HOST', ''),
        'port' => (int) env('SMTP_PORT', 587),
        'secure' => envBool('SMTP_SECURE', false),
        'username' => env('SMTP_USER', ''),
        'password' => env('SMTP_PASS', ''),
        'from' => env('SMTP_FROM', 'AIESEC in Deutschland <noreply@example.com>'),
        'to' => env('SMTP_TO', env('SMTP_USER', 'info@example.com')),
    ];
}

function normalizeBoolean($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    if (is_string($value)) {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'ja', 'on', 'checked'], true);
    }

    return (bool) $value;
}

function readFieldValue(array $body, array $keys): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
    }

    $normalizedKeys = [];
    foreach ($keys as $key) {
        $normalizedKeys[] = strtolower(str_replace([' ', '-', '_'], '', (string) $key));
    }

    foreach ($body as $formKey => $value) {
        $normalizedFormKey = strtolower(str_replace([' ', '-', '_'], '', (string) $formKey));
        if (in_array($normalizedFormKey, $normalizedKeys, true)) {
            return $value;
        }
    }

    return null;
}

function safeTrim($value): ?string
{
    if ($value === null) {
        return null;
    }

    $text = trim((string) $value);
    return $text === '' ? null : $text;
}

function normalizeSource(array $body, array $server): string
{
    $value = safeTrim($body['source'] ?? $body['page'] ?? $body['source-name'] ?? $body['source_name'] ?? null);
    if ($value) {
        return $value;
    }

    $referer = $server['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        if (preg_match('/(?:\/|)([A-Za-z0-9-]+)\.html(?:\?.*)?$/', $referer, $m)) {
            $found = $m[1];
            if ($found && $found !== 'index') {
                return $found;
            }
        }
    }

    return 'website';
}

function normalizeCampaignContext(array $body, array $server): array
{
    $referrerUrl = safeTrim($body['referrer_url'] ?? $body['referrer'] ?? $body['referer_url'] ?? $server['HTTP_REFERER'] ?? null);
    $landingUrl = safeTrim($body['landing_url'] ?? $body['landingUrl'] ?? null);
    if ($landingUrl === null || $landingUrl === '') {
        $landingUrl = (($server['HTTPS'] ?? 'off') === 'on' ? 'https://' : 'http://') . ($server['HTTP_HOST'] ?? 'localhost') . ($server['REQUEST_URI'] ?? '/');
    }

    $queryString = $body['campaign_params'] ?? $body['utm_params'] ?? $body['query_params'] ?? null;
    $params = [];
    if (is_string($queryString) && $queryString !== '') {
        parse_str($queryString, $params);
    }

    $currentQuery = $server['QUERY_STRING'] ?? '';
    if ($currentQuery !== '' && empty($params)) {
        parse_str($currentQuery, $params);
    }

    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'msclkid'] as $key) {
        if (!isset($params[$key])) {
            $params[$key] = safeTrim($body[$key] ?? null) ?? '';
        }
    }

    $params = array_filter($params, static fn ($value) => $value !== null && $value !== '');
    $campaignParams = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return [
        'referrer_url' => $referrerUrl,
        'landing_url' => $landingUrl,
        'campaign_params' => $campaignParams,
        'utm_source' => safeTrim($body['utm_source'] ?? $params['utm_source'] ?? null),
        'utm_medium' => safeTrim($body['utm_medium'] ?? $params['utm_medium'] ?? null),
        'utm_campaign' => safeTrim($body['utm_campaign'] ?? $params['utm_campaign'] ?? null),
        'utm_content' => safeTrim($body['utm_content'] ?? $params['utm_content'] ?? null),
        'utm_term' => safeTrim($body['utm_term'] ?? $params['utm_term'] ?? null),
        'social_source' => safeTrim($body['social'] ?? $body['social_source'] ?? $body['source_social'] ?? $params['utm_source'] ?? null),
    ];
}

function ensureDatabaseAndTable(): void
{
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $dbName = trim((string) env('DB_NAME', 'aiesec_leads'));
    $user = dbUser();
    $pass = dbPassword();
    $safeDb = preg_replace('/[^A-Za-z0-9_]/', '', $dbName) ?: 'aiesec_leads';

    writeAppLog('Ensuring database and table exist.', [
        'host' => $host,
        'port' => $port,
        'database' => $dbName,
        'safe_database' => $safeDb,
    ]);

    $baseDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $base = new PDO($baseDsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $base->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $safeDb));

    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $safeDb), $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $stmt = $pdo->query("SHOW TABLES LIKE 'form_submissions'");
    if ($stmt->fetch() === false) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `form_submissions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `source` VARCHAR(255) NOT NULL,
            `company` VARCHAR(255) DEFAULT NULL,
            `website` VARCHAR(255) DEFAULT NULL,
            `first_name` VARCHAR(255) DEFAULT NULL,
            `last_name` VARCHAR(255) DEFAULT NULL,
            `full_name` VARCHAR(255) DEFAULT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(255) DEFAULT NULL,
            `product_interest` VARCHAR(255) DEFAULT NULL,
            `city` VARCHAR(255) DEFAULT NULL,
            `source_channel` VARCHAR(255) DEFAULT NULL,
            `interest` VARCHAR(255) DEFAULT NULL,
            `profile` VARCHAR(255) DEFAULT NULL,
            `message` TEXT DEFAULT NULL,
            `social_source` VARCHAR(255) DEFAULT NULL,
            `referrer_url` TEXT DEFAULT NULL,
            `landing_url` TEXT DEFAULT NULL,
            `utm_source` VARCHAR(255) DEFAULT NULL,
            `utm_medium` VARCHAR(255) DEFAULT NULL,
            `utm_campaign` VARCHAR(255) DEFAULT NULL,
            `utm_content` VARCHAR(255) DEFAULT NULL,
            `utm_term` VARCHAR(255) DEFAULT NULL,
            `campaign_params` TEXT DEFAULT NULL,
            `consent_contact` TINYINT(1) NOT NULL DEFAULT 0,
            `consent_privacy` TINYINT(1) NOT NULL DEFAULT 0,
            `raw_payload` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_source` (`source`),
            KEY `idx_campaign` (`utm_campaign`),
            KEY `idx_email` (`email`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        writeAppLog('Table form_submissions created.', ['database' => $safeDb]);
    } else {
        writeAppLog('Table form_submissions already exists.', ['database' => $safeDb]);

        $requiredColumns = [
            'source' => 'VARCHAR(255) NOT NULL',
            'company' => 'VARCHAR(255) DEFAULT NULL',
            'website' => 'VARCHAR(255) DEFAULT NULL',
            'first_name' => 'VARCHAR(255) DEFAULT NULL',
            'last_name' => 'VARCHAR(255) DEFAULT NULL',
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'email' => 'VARCHAR(255) NOT NULL',
            'phone' => 'VARCHAR(255) DEFAULT NULL',
            'product_interest' => 'VARCHAR(255) DEFAULT NULL',
            'city' => 'VARCHAR(255) DEFAULT NULL',
            'source_channel' => 'VARCHAR(255) DEFAULT NULL',
            'interest' => 'VARCHAR(255) DEFAULT NULL',
            'profile' => 'VARCHAR(255) DEFAULT NULL',
            'message' => 'TEXT DEFAULT NULL',
            'social_source' => 'VARCHAR(255) DEFAULT NULL',
            'referrer_url' => 'TEXT DEFAULT NULL',
            'landing_url' => 'TEXT DEFAULT NULL',
            'utm_source' => 'VARCHAR(255) DEFAULT NULL',
            'utm_medium' => 'VARCHAR(255) DEFAULT NULL',
            'utm_campaign' => 'VARCHAR(255) DEFAULT NULL',
            'utm_content' => 'VARCHAR(255) DEFAULT NULL',
            'utm_term' => 'VARCHAR(255) DEFAULT NULL',
            'campaign_params' => 'TEXT DEFAULT NULL',
            'consent_contact' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'consent_privacy' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'raw_payload' => 'LONGTEXT DEFAULT NULL',
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ];

        $existingColumns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `form_submissions`') as $column) {
            $existingColumns[$column['Field']] = true;
        }

        foreach ($requiredColumns as $column => $definition) {
            if (isset($existingColumns[$column])) {
                continue;
            }

            $pdo->exec(sprintf('ALTER TABLE `form_submissions` ADD COLUMN `%s` %s', $column, $definition));
            writeAppLog('Added missing column to form_submissions.', ['database' => $safeDb, 'column' => $column]);
        }
    }
}

function dbConnect(): PDO
{
    ensureDatabaseAndTable();

    $dsn = dbDsn();
    $user = dbUser();
    $pass = dbPassword();

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function sendSmtpMail(array $config, string $subject, string $body, string $to, string $from): bool
{
    $host = $config['host'];
    $port = $config['port'];
    $secure = $config['secure'];
    $user = $config['username'];
    $pass = $config['password'];

    if ($host === '' || $user === '' || $pass === '') {
        return false;
    }

    $connectionHost = $host;
    if ($secure && (int) $port === 465) {
        $connectionHost = 'ssl://' . $host;
    }

    $socket = fsockopen($connectionHost, $port, $errno, $errstr, 20);
    if ($socket === false) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, 20);

    $read = function () use ($socket) {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;

            if (preg_match('/^\d{3} /', $line) === 1) {
                break;
            }

            if (preg_match('/^\d{3}-/', $line) === 1) {
                continue;
            }
        }
        return $response;
    };

    $write = function (string $command) use ($socket) {
        fwrite($socket, $command . "\r\n");
    };

    $greeting = $read();
    if (strpos($greeting, '220') !== 0) {
        throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
    }

    $write('EHLO localhost');
    $read();

    if ($secure) {
        $write('STARTTLS');
        $resp = $read();
        if (strpos($resp, '220') !== 0) {
            throw new RuntimeException('STARTTLS failed: ' . trim($resp));
        }

        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $write('EHLO localhost');
        $read();
    }

    $write('AUTH LOGIN');
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        throw new RuntimeException('SMTP AUTH LOGIN failed: ' . trim($resp));
    }

    $write(base64_encode($user));
    $resp = $read();
    if (strpos($resp, '334') !== 0) {
        throw new RuntimeException('SMTP username rejected: ' . trim($resp));
    }

    $write(base64_encode($pass));
    $resp = $read();
    if (strpos($resp, '235') !== 0) {
        throw new RuntimeException('SMTP password rejected: ' . trim($resp));
    }

    $mailFrom = $from;
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        $mailFrom = $matches[1];
    }

    $write('MAIL FROM:<' . $mailFrom . '>');
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        throw new RuntimeException('MAIL FROM failed: ' . trim($resp));
    }

    $write('RCPT TO:<' . $to . '>');
    $resp = $read();
    if (strpos($resp, '250') !== 0 && strpos($resp, '251') !== 0) {
        throw new RuntimeException('RCPT TO failed: ' . trim($resp));
    }

    $write('DATA');
    $resp = $read();
    if (strpos($resp, '354') !== 0) {
        throw new RuntimeException('DATA failed: ' . trim($resp));
    }

    $message = "From: {$from}\r\n";
    $message .= "To: {$to}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $body . "\r\n.";

    fwrite($socket, $message . "\r\n");
    $resp = $read();
    if (strpos($resp, '250') !== 0) {
        throw new RuntimeException('SMTP DATA command failed: ' . trim($resp));
    }

    $write('QUIT');
    $read();
    fclose($socket);

    return true;
}
