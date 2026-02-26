<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use BattleVue\Db;

$db = Db::pdo();

echo "Running seed...\n";

$db->exec('INSERT INTO users (username, email, password_hash, display_name)
VALUES ("demo", "demo@example.com", "$2y$10$5DkKewrY35iW4jA3Nrv7fOnhp6agQiyVE8wH0wNZ3Rlh2HhQ9bD5m", "Demo User")
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)');

echo "Seed complete.\n";
