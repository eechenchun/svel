<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Maintenance.php
 * @desc 清理会话、日志、旧备份与缓存，避免长期运行占满磁盘
 */

namespace Svel\Core;

use Svel\Models\Option;

final class Maintenance
{
    /** @return array{sessions:int,logs:int,backups:int,cache:int,audit:int} */
    public static function run(): array
    {
        $storage = rtrim(str_replace('\\', '/', (string)Config::get('storage.dir', SVEL_ROOT . '/storage')), '/');
        $now = time();
        $stats = ['sessions' => 0, 'logs' => 0, 'backups' => 0, 'cache' => 0, 'audit' => 0];

        foreach (glob($storage . '/sessions/sess_*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $now - 7 * 86400 && @unlink($file)) {
                $stats['sessions']++;
            }
        }

        foreach (glob($storage . '/logs/*.log') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $now - 30 * 86400 && @unlink($file)) {
                $stats['logs']++;
            }
        }

        $backups = glob($storage . '/backups/cms-*.zip') ?: [];
        usort($backups, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($backups, 5) as $file) {
            if (@unlink($file)) {
                $stats['backups']++;
            }
        }

        try {
            $stats['audit'] = Db::inst()->exec(
                'DELETE FROM ' . Db::inst()->qi(Db::inst()->table('audit')) . ' WHERE ' . Db::inst()->qi('created_at') . ' < ?',
                [$now - 180 * 86400]
            );
        } catch (\Throwable) {
            $stats['audit'] = 0;
        }
        $stats['cache'] = Cache::gc((int)option('cache.ttl', 3600));
        Option::set('maintenance_at', (string)$now);
        return $stats;
    }
}