<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/View.php
 * @desc 模板渲染：当前主题优先，缺失模板回退默认主题；用闭包隔离作用域，避免污染
 */

namespace Svel\Core;

final class View
{
    private static string $themeDir = '';
    private static string $defaultDir = '';
    private static string $adminDir = '';

    public static function init(string $root, string $themeSlug): void
    {
        self::$defaultDir = rtrim(str_replace('\\', '/', $root . '/themes/default'), '/');
        self::$themeDir = $themeSlug === '' || $themeSlug === 'default'
            ? self::$defaultDir
            : rtrim(str_replace('\\', '/', $root . '/themes/' . basename($themeSlug)), '/');
        self::$adminDir = rtrim(str_replace('\\', '/', $root . '/app/views/admin'), '/');
    }

    public static function themeDir(): string
    {
        return self::$themeDir;
    }

    public static function render(string $template, array $data = []): string
    {
        $file = self::locate($template);
        if ($file === null) {
            throw new \RuntimeException('模板不存在：' . $template);
        }
        return self::include($file, $data);
    }

    /** 后台模板不带主题回退，只在自己的目录里找 */
    public static function admin(string $template, array $data = []): string
    {
        $file = self::$adminDir . '/' . basename($template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('后台模板不存在：' . $template);
        }
        return self::include($file, $data);
    }

    /** 主题内片段，可带参数数组 */
    public static function partial(string $name, array $data = []): string
    {
        return self::render('partials/' . $name, $data);
    }

    public static function exists(string $template): bool
    {
        return self::locate($template) !== null;
    }

    private static function locate(string $template): ?string
    {
        $name = str_replace('\\', '/', $template);
        $name = preg_replace('#\.php$#', '', $name) ?? $name;
        $name = ltrim(str_replace('..', '', $name), '/');

        foreach ([self::$themeDir, self::$defaultDir] as $dir) {
            if ($dir !== '' && is_file($dir . '/' . $name . '.php')) {
                return $dir . '/' . $name . '.php';
            }
        }
        return null;
    }

    private static function include(string $file, array $data): string
    {
        $render = static function () use ($file, $data): void {
            extract($data, EXTR_SKIP);
            include $file;
        };
        ob_start();
        try {
            $render();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}