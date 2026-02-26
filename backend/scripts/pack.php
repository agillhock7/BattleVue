<?php

declare(strict_types=1);

$repoRoot = realpath(__DIR__ . '/../..');
if (!$repoRoot) {
    throw new RuntimeException('Cannot determine repository root.');
}

$backendRoot = $repoRoot . '/backend';
$apiRoot = $repoRoot . '/api';

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function copyDir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }

    $items = scandir($src);
    if ($items === false) {
        throw new RuntimeException('Cannot read ' . $src);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if ($item === 'config.php') {
            continue;
        }
        $from = $src . '/' . $item;
        $to = $dst . '/' . $item;

        if (is_dir($from)) {
            copyDir($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

if (is_dir($apiRoot)) {
    rrmdir($apiRoot);
}
mkdir($apiRoot, 0775, true);

copyDir($backendRoot . '/public', $apiRoot);
copyDir($backendRoot . '/src', $apiRoot . '/src');
copyDir($backendRoot . '/scripts', $apiRoot . '/scripts');
copyDir($backendRoot . '/migrations', $apiRoot . '/migrations');
copyDir($backendRoot . '/config', $apiRoot . '/config');
copy($backendRoot . '/.htaccess', $apiRoot . '/.htaccess');

echo "Packed API into {$apiRoot}\n";
