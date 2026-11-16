<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Cache.php
 * @desc 页面缓存：两级目录分片 + 临时文件 rename 原子落盘 + 非阻塞文件锁，防止并发写坏页面
 */

namespace Svel\Core;

final class Cache
{
    private static string $dir = '';
    private static bool $enabled = false;

    public static function init(string $storageDir, int $ttl): void
    {
        self::$dir = rtrim(str_replace('\\', '/', $storageDir), '/') . '/cache';
        self::$enabled = $ttl > 0;
        if (self::$enabled && !is_dir(self::$dir)) {
            @mkdir(self::$dir, 0755, true);
        }
    }

    /** 后台清缓存可能早于 init，这里按配置补一次目录 */
    private static function ensureDir(): void
    {
        if (self::$dir === '') {
            $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
            self::$dir = rtrim(str_replace('\\', '/', $dir), '/') . '/cache';
        }
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function keyFor(string $url): string
    {
        return sha1($url);
    }

private static function base(string $key): string
    {
        return self::$dir . '/' . substr($key, 0, 2) . '/' . substr($key, 2, 2) . '/' . $key;
    }

    /** 统计缓存文件数量，作为条目上限的初始值 */
    private static function filesCount(): int
    {
        if (self::$dir === '' || !is_dir(self::$dir)) {
            return 0;
        }
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $count++;
            }
        }
        return $count;
    }

    /** 预留一个缓存条目名额；超过上限时不再写入新页面 */
    private static function reserveSlot(): bool
    {
        $max = (int)Config::get('cache.max_files', 20000);
        if ($max <= 0 || self::$dir === '') {
            return true;
        }
        $file = self::$dir . '/.count';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return true;
        }
        $raw = trim((string)stream_get_contents($fp));
        $count = $raw === '' ? self::filesCount() : (int)$raw;
        if ($count >= $max) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)($count + 1));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    private static function releaseSlot(): void
    {
        if (self::$dir === '') {
            return;
        }
        $file = self::$dir . '/.count';
        if (!is_file($file)) {
            return;
        }
        $fp = @fopen($file, 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false) {
                fclose($fp);
            }
            return;
        }
        $raw = trim((string)stream_get_contents($fp));
        $count = max(0, (int)$raw - 1);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$count);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public static function get(string $url): ?array
    {
        if (!self::$enabled) {
            return null;
        }
        $key = self::keyFor($url);
        $html = self::base($key) . '.html';
        $metaFile = self::base($key) . '.json';
        if (!is_file($html) || !is_file($metaFile)) {
            return null;
        }
        $meta = json_decode((string)@file_get_contents($metaFile), true);
        if (!is_array($meta) || (int)($meta['expire'] ?? 0) < time()) {
            self::forget($url);
            return null;
        }
        $body = @file_get_contents($html);
        if ($body === false) {
            return null;
        }
        return [
            'body'    => $body,
            'status'  => (int)($meta['status'] ?? 200),
            'headers' => (array)($meta['headers'] ?? []),
            'created' => (int)($meta['created'] ?? 0),
        ];
    }

    /**
     * @param array<string,string> $headers 只保存可重放的响应头
     */
    public static function put(string $url, string $body, int $status, array $headers, int $ttl): bool
    {
        if (!self::$enabled || $ttl <= 0) {
            return false;
        }
$key = self::keyFor($url);
        $base = self::base($key);
        $dir = dirname($base);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $isNew = !is_file($base . '.html');
        if ($isNew && !self::reserveSlot()) {
            return false;
        }

        $lock = @fopen($base . '.lock', 'c');
        if ($lock === false) {
            if ($isNew) {
                self::releaseSlot();
            }
            return false;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            if ($isNew) {
                self::releaseSlot();
            }
            return false;
        }

        try {
            if (!self::atomicWrite($base . '.html', $body)) {
                if ($isNew) {
                    self::releaseSlot();
                }
                return false;
            }
            $meta = json_encode([
                'status'  => $status,
                'headers' => $headers,
                'created' => time(),
                'expire'  => time() + $ttl,
                'url'     => $url,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($meta === false) {
                if ($isNew) {
                    self::releaseSlot();
                }
                return false;
            }
            $ok = self::atomicWrite($base . '.json', $meta);
            if (!$ok && $isNew) {
                self::releaseSlot();
            }
            return $ok;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function atomicWrite(string $target, string $content): bool
    {
        $tmp = $target . '.' . getmypid() . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }
        if (@rename($tmp, $target)) {
            return true;
        }
        // Windows 下 rename 覆盖可能失败，退化为拷贝后删临时文件
        $ok = @copy($tmp, $target);
        @unlink($tmp);
        return $ok;
    }

    public static function forget(string $url): void
    {
        self::ensureDir();
        if (self::$dir === '') {
            return;
        }
$base = self::base(self::keyFor($url));
        $hadHtml = is_file($base . '.html');
        foreach (['.html', '.json', '.lock'] as $ext) {
            if (is_file($base . $ext)) {
                @unlink($base . $ext);
            }
        }
        if ($hadHtml) {
            self::releaseSlot();
        }
    }

    /** 清空全部页面缓存，后台改设置或切主题后调用 */
    public static function flush(): int
    {
        self::ensureDir();
        if (self::$dir === '' || !is_dir(self::$dir)) {
            return 0;
        }
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }
            if (@unlink($file->getPathname())) {
                $count++;
            }
        }
        return $count;
    }

    /** 清理过期缓存，请求内按概率触发，避免共享主机没有 cron */
    public static function gc(int $ttl): int
    {
        if (!self::$enabled || !is_dir(self::$dir)) {
            return 0;
        }
if (random_int(1, 100) !== 1) {
            return 0;
        }
        $deadline = time() - max(60, $ttl);
        $lockDeadline = time() - max(3600, $ttl * 2);
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() === 'lock') {
                if ($file->getMTime() < $lockDeadline) {
                    @unlink($file->getPathname());
                }
                continue;
            }
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $meta = json_decode((string)@file_get_contents($file->getPathname()), true);
            if (is_array($meta) && (int)($meta['expire'] ?? 0) < $deadline) {
                $html = substr($file->getPathname(), 0, -5) . '.html';
                if (is_file($html)) {
                    @unlink($html);
                    self::releaseSlot();
                }
                if (@unlink($file->getPathname())) {
                    $count++;
                }
            }
        }
        return $count;
    }
}