<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use BattleVue\Db;

$db = Db::pdo();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration_name VARCHAR(255) NOT NULL UNIQUE, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = [];
foreach ($db->query('SELECT migration_name FROM schema_migrations')->fetchAll() as $row) {
    $applied[$row['migration_name']] = true;
}

$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "Skipping {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$name}");
    }

    echo "Applying {$name}\n";
    $db->beginTransaction();
    try {
        $db->exec($sql);
        $stmt = $db->prepare('INSERT INTO schema_migrations (migration_name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

echo "Done.\n";
