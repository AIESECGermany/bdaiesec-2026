<?php
require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

function safeEcho(string $label, ?string $value): void
{
    echo $label . ': ' . ($value ?? '[empty]') . PHP_EOL;
}

echo "AIESEC FORM DEBUG\n";
echo "=================\n";

safeEcho('DB_HOST', env('DB_HOST', 'not set'));
safeEcho('DB_PORT', env('DB_PORT', 'not set'));
safeEcho('DB_NAME', env('DB_NAME', 'not set'));
safeEcho('DB_USER', env('DB_USER', 'not set'));
safeEcho('SMTP_HOST', env('SMTP_HOST', 'not set'));
safeEcho('SMTP_USER', env('SMTP_USER', 'not set'));
safeEcho('SMTP_TO', env('SMTP_TO', 'not set'));

echo "\nChecking database connection...\n";

try {
    $pdo = dbConnect();
    echo "DB connection: OK\n";

    $stmt = $pdo->query("SHOW TABLES LIKE 'form_submissions'");
    $tableExists = $stmt->fetch() !== false;
    echo "Table form_submissions exists: " . ($tableExists ? 'YES' : 'NO') . "\n";

    if (!$tableExists) {
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
            `consent_contact` TINYINT(1) NOT NULL DEFAULT 0,
            `consent_privacy` TINYINT(1) NOT NULL DEFAULT 0,
            `raw_payload` JSON DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_source` (`source`),
            KEY `idx_email` (`email`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        echo "Table form_submissions created successfully.\n";
    }
} catch (Throwable $e) {
    echo "DB connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nThis is usually one of:\n";
    echo "- DB_USER / DB_PASSWORD are wrong\n";
    echo "- DB_NAME does not exist or no permission\n";
    echo "- MariaDB/MySQL service is not running\n";
}

echo "\nChecking SMTP config...\n";
$cfg = smtpConfig();
if ($cfg['host'] === '' || $cfg['username'] === '' || $cfg['password'] === '') {
    echo "SMTP: NOT CONFIGURED\n";
} else {
    echo "SMTP: Configured\n";
    echo "SMTP sender: " . $cfg['from'] . "\n";
    echo "SMTP recipient: " . $cfg['to'] . "\n";
}
