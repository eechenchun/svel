<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Hook.php
 * @desc 钩子：钩子名 → 有序插件入口列表；请求未触发的钩子，其入口文件一个字节都不读
 */

namespace Svel\Core;

use Svel\Models\Plugin;

final class Hook
{
    /** @var array<string,array<int,array{plugin:string,file:string,priority:int}>> */
    private static array $map = [];

    /** @var array<string,array<int,array{0:int,1:int,2:callable}>> */
    private static array $callbacks = [];

    /** @var array<string,bool> */
    private static array $loadedFiles = [];

    private static bool $booted = false;
    private static int $seq = 0;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        try {
            self::$map = self::map();
        } catch (\Throwable $e) {
            Log::warn('钩子映射加载失败：' . $e->getMessage());
            self::$map = [];
        }
    }

    /** @return array<string,array<int,array{plugin:string,file:string,priority:int}>> */
    private static function map(): array
    {
        $file = self::cacheFile();
        if (is_file($file)) {
            $data = @include $file;
            if (is_array($data)) {
                return $data;
            }
        }
        return self::rebuild();
    }

    /** 重新扫描已启用插件，重写钩子缓存；启用/停用/安装插件后必须调用 */
    public static function rebuild(): array
    {
        $map = [];
        foreach (Plugin::enabledWithHooks() as $row) {
            $slug = (string)$row['slug'];
            $entry = (string)($row['entry'] ?? 'plugin.php');
            $hooks = json_decode((string)($row['hooks'] ?? '[]'), true);
            if (!is_array($hooks)) {
                continue;
            }
            $basePriority = (int)($row['priority'] ?? 10) ?: 10;

            if (array_is_list($hooks)) {
                foreach ($hooks as $name) {
                    if (is_string($name) && $name !== '') {
                        $map[$name][] = ['plugin' => $slug, 'file' => $entry, 'priority' => $basePriority];
                    }
                }
            } else {
                foreach ($hooks as $name => $priority) {
                    if (is_string($name) && $name !== '') {
                        $map[$name][] = ['plugin' => $slug, 'file' => $entry, 'priority' => (int)$priority ?: $basePriority];
                    }
                }
            }
        }

        foreach ($map as $name => $list) {
            usort($list, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
            $map[$name] = $list;
        }

        $file = self::cacheFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir)) {
            $php = "<?php\n\n// SvelCMS 钩子缓存，插件启用状态变化时自动重建\nreturn "
                . var_export($map, true) . ";\n";
            $tmp = $file . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, $php) !== false) {
                if (!@rename($tmp, $file)) {
                    @copy($tmp, $file);
                    @unlink($tmp);
                }
            }
        }

        self::$map = $map;
        self::$callbacks = [];
        self::$loadedFiles = [];
        self::$booted = true;
        return $map;
    }

    private static function cacheFile(): string
    {
        $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        return rtrim(str_replace('\\', '/', $dir), '/') . '/cache/hooks.php';
    }

    /** 载入该钩子对应的插件入口文件（每个文件每个请求只加载一次） */
    private static function ensure(string $hook): void
    {
        foreach (self::$map[$hook] ?? [] as $entry) {
            $path = SVEL_ROOT . '/plugins/' . $entry['plugin'] . '/' . $entry['file'];
            if (isset(self::$loadedFiles[$path])) {
                continue;
            }
            self::$loadedFiles[$path] = true;
            if (is_file($path)) {
                try {
                    include_once $path;
                } catch (\Throwable $e) {
                    Log::error('插件加载失败 ' . $entry['plugin'] . '：' . $e->getMessage());
                }
            }
        }
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$callbacks[$hook][] = [$priority, self::$seq++, $callback];
    }

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::addFilter($hook, $callback, $priority);
    }

    public static function filter(string $hook, mixed $value, mixed ...$args): mixed
    {
        self::ensure($hook);
        $list = self::$callbacks[$hook] ?? [];
        if ($list === []) {
            return $value;
        }
        usort($list, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        foreach ($list as [, , $cb]) {
            $value = $cb($value, ...$args);
        }
        return $value;
    }

    public static function action(string $hook, mixed ...$args): void
    {
        self::ensure($hook);
        $list = self::$callbacks[$hook] ?? [];
        if ($list === []) {
            return;
        }
        usort($list, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        foreach ($list as [, , $cb]) {
            $cb(...$args);
        }
    }

    /** 已注册钩子名，用于后台展示 */
    public static function names(): array
    {
        return array_keys(self::$map);
    }
}