<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Log.php
 * @desc 错误日志：单行写入 + 文件锁，避免共享主机并发写交错
 */

namespace Svel\Core;

final class Log
{
    private static ?string $dir = null;

    public static function init(string $dir): void
    {
        self::$dir = rtrim(str_replace('\\', '/', $dir), '/');
    }

    public static function error(string $message, string $file = '', int $line = 0): void
    {
        self::write('ERROR', $message, $file, $line);
    }

    public static function warn(string $message, string $file = '', int $line = 0): void
    {
        self::write('WARN', $message, $file, $line);
    }

    public static function write(string $level, string $message, string $file = '', int $line = 0): void
    {
        $dir = self::$dir ?? (defined('SVEL_ROOT') ? SVEL_ROOT . '/storage/logs' : sys_get_temp_dir());
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('[' . $level . '] ' . $message);
            return;
        }
        $where = $file !== '' ? basename($file) . ($line > 0 ? ':' . $line : '') : '-';
        $row = date('Y-m-d H:i:s') . ' | ' . $level . ' | ' . $where . ' | '
            . str_replace(["\r", "\n"], ' ', $message) . "\n";
        $path = $dir . '/error-' . date('Ymd') . '.log';

        $fp = @fopen($path, 'ab');
        if ($fp === false) {
            error_log($row);
            return;
        }
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $row);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /** 返回最近日志文件路径，供后台展示 */
    public static function latest(): ?string
    {
        $dir = self::$dir ?? (defined('SVEL_ROOT') ? SVEL_ROOT . '/storage/logs' : '');
        if ($dir === '' || !is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/error-*.log') ?: [];
        if ($files === []) {
            return null;
        }
        sort($files);
        return end($files);
    }
}