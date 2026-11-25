<?php

/**
 * Create MySQL database without mysql CLI (OSPanel fallback).
 * Usage: php scripts/provision-database.php [host] [user] [password] [database]
 */
declare(strict_types=1);

$host = $argv[1] ?? '';
$user = $argv[2] ?? 'root';
$password = $argv[3] ?? '';
$database = $argv[4] ?? 'saas_crm';

function ospanel_bind_hosts(): array
{
    $hosts = [];
    foreach (glob('C:/OSPanel/modules/MySQL-*/my.ini') ?: [] as $ini) {
        $content = @file_get_contents($ini);
        if ($content === false) {
            continue;
        }
        if (preg_match('/^\s*bind_address\s*=\s*(\S+)/mi', $content, $matches)) {
            $hosts[] = trim($matches[1], "\"'");
        }
    }

    return $hosts;
}

$hosts = array_values(array_unique(array_filter(array_merge(
    $host !== '' ? [$host] : [],
    ospanel_bind_hosts(),
    ['127.0.1.21', '127.0.0.1', 'localhost']
))));

foreach ($hosts as $tryHost) {
    try {
        $dsn = sprintf('mysql:host=%s;port=3306;charset=utf8mb4', $tryHost);
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $safeName = str_replace('`', '``', $database);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        fwrite(STDOUT, "OK:{$tryHost}\n");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Host {$tryHost}: {$e->getMessage()}\n");
    }
}

fwrite(STDERR, "Could not create database via PHP PDO.\n");
exit(1);
