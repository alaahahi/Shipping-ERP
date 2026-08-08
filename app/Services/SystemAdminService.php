<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemAdminService
{
    public function runMigrate(User $actor): string
    {
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        Log::info('System migrate executed from settings UI.', [
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'exit_code' => $exitCode,
            'output' => $output,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'Migration failed.');
        }

        return $output !== '' ? $output : 'Nothing to migrate.';
    }

    /**
     * @return array{
     *     file: string,
     *     lines: list<array{text: string, level: string}>,
     *     total: int
     * }
     */
    public function tailLog(int $limit = 200, ?string $level = null): array
    {
        $path = $this->latestLogPath();
        $rawLines = $this->readLastLines($path, max(50, min($limit, 1000)));

        $parsed = array_map(function (string $line): array {
            return [
                'text' => $line,
                'level' => $this->detectLevel($line),
            ];
        }, $rawLines);

        if ($level) {
            $wanted = strtoupper($level);
            $parsed = array_values(array_filter(
                $parsed,
                fn (array $row): bool => $row['level'] === $wanted
            ));
        }

        return [
            'file' => basename($path),
            'lines' => $parsed,
            'total' => count($parsed),
        ];
    }

    public function clearLogs(User $actor): void
    {
        $dir = realpath(storage_path('logs'));
        if (! $dir || ! is_dir($dir)) {
            throw new RuntimeException('Logs directory is missing.');
        }

        foreach (File::files($dir) as $file) {
            if (! str_ends_with($file->getFilename(), '.log')) {
                continue;
            }

            $real = $file->getRealPath();
            if (! $real || ! str_starts_with($real, $dir)) {
                continue;
            }

            File::put($real, '');
        }

        Log::warning('Application logs cleared from settings UI.', [
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
        ]);
    }

    public function downloadLog(): StreamedResponse
    {
        $path = $this->latestLogPath();

        return response()->streamDownload(function () use ($path): void {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        }, basename($path), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function latestLogPath(): string
    {
        $dir = storage_path('logs');
        $laravel = $dir.DIRECTORY_SEPARATOR.'laravel.log';

        if (is_file($laravel)) {
            return $laravel;
        }

        $files = collect(File::files($dir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.log'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($files->isEmpty()) {
            throw new RuntimeException('No log files found.');
        }

        return $files->first()->getPathname();
    }

    /**
     * @return list<string>
     */
    private function readLastLines(string $path, int $limit): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Log file is not readable.');
        }

        $real = realpath($path);
        $logsDir = realpath(storage_path('logs'));

        if (! $real || ! $logsDir || ! str_starts_with($real, $logsDir)) {
            throw new RuntimeException('Invalid log path.');
        }

        $file = new \SplFileObject($real, 'r');
        $file->seek(PHP_INT_MAX);
        $last = $file->key();
        $start = max(0, $last - $limit);

        $lines = [];
        for ($i = $start; $i <= $last; $i++) {
            $file->seek($i);
            $line = rtrim((string) $file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function detectLevel(string $line): string
    {
        foreach (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $level) {
            if (str_contains($line, '.'.$level.':') || str_contains($line, '.'.$level.' ')) {
                return $level;
            }
        }

        return 'INFO';
    }
}
