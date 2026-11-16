<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Redirect.php
 * @desc slug 变更后的 301 映射：落数据目录 redirect.php，不建表、不进 option，链路自动压缩
 */

namespace Svel\Core;

final class Redirect
{
    private static ?array $map = null;
    private static string $file = '';

    public static function init(string $storageDir): void
    {
        self::$file = rtrim(str_replace('\\', '/', $storageDir), '/') . '/redirect.php';
    }

    private static function file(): string
    {
        if (self::$file !== '') {
            return self::$file;
        }
        $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        return rtrim(str_replace('\\', '/', $dir), '/') . '/redirect.php';
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        $file = self::file();
        if (!is_file($file)) {
            return self::$map = [];
        }
        $data = @include $file;
        return self::$map = is_array($data) ? $data : [];
    }

    /** 查旧路径对应的新路径，找不到返回 null */
    public static function find(string $path): ?string
    {
        $key = self::normalize($path);
        $map = self::all();
        $target = $map[$key] ?? null;
        return is_string($target) && $target !== '' ? $target : null;
    }

    public static function normalize(string $path): string
    {
        $path = strtolower(trim($path));
        $path = ltrim($path, '/');
        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    /**
     * 写入一条旧→新映射。已存在反向链路时抛异常，避免循环 301。
     */
    public static function add(string $old, string $new): void
    {
        $from = self::normalize($old);
        $to = self::normalize($new);
        if ($from === '' || $to === '' || $from === $to) {
            return;
        }

        $map = self::all();

        // 防循环：从目标出发顺着链路走，若能回到来源则拒绝
        $cursor = $to;
        $guard = 0;
        while (isset($map[$cursor]) && $guard < 128) {
            $cursor = (string)$map[$cursor];
            if (self::normalize($cursor) === $from) {
                throw new \RuntimeException('新旧地址形成了循环跳转，已拒绝保存');
            }
            $guard++;
        }

        // 链路压缩：所有原来指向 from 的旧地址，直接改指 to
        foreach ($map as $key => $value) {
            if (self::normalize((string)$value) === $from) {
                $map[$key] = $to;
            }
        }

        // 目标已有映射时，直接穿到最终地址，绝不产生多跳
        $final = $to;
        $guard = 0;
        while (isset($map[$final]) && $guard < 128) {
            $final = (string)$map[$final];
            $guard++;
        }

        $map[$from] = $final;
        self::write($map);
    }

    public static function forget(string $path): void
    {
        $map = self::all();
        $key = self::normalize($path);
        if (!isset($map[$key])) {
            return;
        }
        unset($map[$key]);
        self::write($map);
    }

    /** 删除所有指向某个目标路径的旧地址映射 */
    public static function removeTarget(string $target): int
    {
        $map = self::all();
        $normalized = self::normalize($target);
        $removed = 0;
        foreach ($map as $key => $value) {
            if (self::normalize((string)$value) === $normalized) {
                unset($map[$key]);
                $removed++;
            }
        }
        if ($removed > 0) {
            self::write($map);
        }
        return $removed;
    }

    /** @param array<string,string> $map */
    private static function write(array $map): void
    {
        $file = self::file();
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法写入跳转映射，数据目录不可写：' . $dir);
        }

        $php = "<?php\n\n// 由 SvelCMS 自动维护：旧地址 → 新地址，请勿手工整理\nreturn "
            . var_export($map, true) . ";\n";
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $php) === false) {
            throw new \RuntimeException('无法写入跳转映射临时文件');
        }
        if (!@rename($tmp, $file)) {
            @copy($tmp, $file);
            @unlink($tmp);
        }
        self::$map = $map;
    }

    public static function count(): int
    {
        return count(self::all());
    }
}