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
        return in_array($v, ['1', 'true', 'yes', 'ja', 'on'], true);
    }

    return (bool) $value;
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

function dbConnect(): PDO
{
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

    $socket = fsockopen($secure ? 'tls://' . $host : $host, $port, $errno, $errstr, 20);
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
            if (preg_match('/\r\n$/', $line) === 1) {
                break;
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

    $write('MAIL FROM:<' . $from . '>');
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
