<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Throttle.php
 * @desc 简单滑动窗口限速，用于站内搜索等公开高频入口
 */

namespace Svel\Core;

final class Throttle
{
    public static function allow(string $key, int $max = 30, int $window = 60): bool
    {
        $dir = rtrim(str_replace('\\', '/', (string)Config::get('storage.dir', SVEL_ROOT . '/storage')), '/') . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/throttle-' . md5($key) . '.json';
        $now = time();
        $hits = [];
        if (is_file($file)) {
            $data = json_decode((string)@file_get_contents($file), true);
            if (is_array($data)) {
                $hits = array_values(array_filter(array_map('intval', $data), static fn(int $t): bool => $t > $now - $window));
            }
        }
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        return true;
    }
}