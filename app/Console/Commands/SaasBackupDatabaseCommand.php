<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SaasBackupDatabaseCommand extends Command
{
    protected $signature = 'saas:backup-database {--output= : Output directory (default storage/app/backups)}';

    protected $description = 'Dump MySQL database to storage/app/backups (Phase 4 ops)';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? '') !== 'mysql') {
            $this->error('saas:backup-database supports mysql driver only.');

            return self::FAILURE;
        }

        $outputDir = $this->option('output') ?: storage_path('app/backups');
        File::ensureDirectoryExists($outputDir);

        $filename = sprintf(
            'saas-%s-%s.sql.gz',
            $config['database'] ?? 'crm',
            now()->format('Ymd-His'),
        );

        $path = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        $host = escapeshellarg((string) ($config['host'] ?? '127.0.0.1'));
        $port = (int) ($config['port'] ?? 3306);
        $database = escapeshellarg((string) ($config['database'] ?? ''));
        $username = escapeshellarg((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');

        $mysqldump = trim((string) shell_exec('command -v mysqldump') ?: '');

        if ($mysqldump === '') {
            $this->error('mysqldump not found in PATH. Install MySQL client tools or run manual backup — see docs/sync/runbook-backup-restore.md');

            return self::FAILURE;
        }

        $passwordArg = $password !== '' ? '-p'.escapeshellarg($password) : '';

        $command = sprintf(
            '%s -h %s -P %d -u %s %s %s | gzip > %s',
            escapeshellarg($mysqldump),
            $host,
            $port,
            $username,
            $passwordArg,
            $database,
            escapeshellarg($path),
        );

        $exitCode = 0;
        system($command, $exitCode);

        if ($exitCode !== 0 || ! is_file($path)) {
            $this->error('Backup failed (exit '.$exitCode.').');

            return self::FAILURE;
        }

        $this->info('Backup written: '.$path);

        return self::SUCCESS;
    }
}
