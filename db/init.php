<?php
require __DIR__ . '/../api/config.php';

try {
    $pdo = dbConnect();
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read schema.sql');
    }

    $pdo->exec($sql);
    echo "Database and tables initialized successfully.\n";
} catch (Throwable $e) {
    echo 'Database init failed: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
