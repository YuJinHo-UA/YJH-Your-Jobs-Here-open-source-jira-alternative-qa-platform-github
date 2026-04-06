<?php
declare(strict_types=1);

function yjh_logs_dir(): string
{
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function yjh_log_file_path(string $channel): string
{
    $safe = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower($channel));
    return yjh_logs_dir() . '/' . $safe . '.log';
}

function yjh_log_write(string $channel, string $message): void
{
    $line = sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL);
    @file_put_contents(yjh_log_file_path($channel), $line, FILE_APPEND | LOCK_EX);
}

function yjh_log_read_tail(string $channel, int $maxLines = 120): array
{
    $path = yjh_log_file_path($channel);
    if (!is_file($path)) {
        return [];
    }
    $content = (string)@file_get_contents($path);
    if ($content === '') {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
    if ($maxLines > 0 && count($lines) > $maxLines) {
        $lines = array_slice($lines, -$maxLines);
    }
    return $lines;
}

function yjh_log_clear(string $channel): void
{
    @file_put_contents(yjh_log_file_path($channel), '');
}

