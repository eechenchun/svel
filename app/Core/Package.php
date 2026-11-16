<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Package.php
 * @desc 应用包安装：防止路径穿越、限制文件数与解压体积、拒绝对已存在目录覆盖
 */

namespace Svel\Core;

final class Package
{
    /**
     * @param string $kind plugin|theme，仅用于提示文案
     * @return array{ok:bool,msg:string,slug:string}
     */
    public static function install(string $zipFile, string $kind, string $targetRoot, string $manifestName, bool $replace = false): array
    {
        if (!class_exists('\ZipArchive')) {
            return ['ok' => false, 'msg' => 'PHP 未启用 zip 扩展，无法解压安装包', 'slug' => ''];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['ok' => false, 'msg' => '安装包无法打开，可能已损坏', 'slug' => ''];
        }
        if ($zip->numFiles > 800) {
            $zip->close();
            return ['ok' => false, 'msg' => '安装包文件数量过多，已拒绝', 'slug' => ''];
        }

        $targetRoot = rtrim(str_replace('\\', '/', $targetRoot), '/');
        $tmp = $targetRoot . '/.incoming-' . bin2hex(random_bytes(4));
        if (!@mkdir($tmp, 0755, true)) {
            $zip->close();
            return ['ok' => false, 'msg' => '无法创建临时目录，请检查目录权限', 'slug' => ''];
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                self::removeDir($tmp);
                $zip->close();
                return ['ok' => false, 'msg' => '安装包内含有非法路径，已拒绝安装', 'slug' => ''];
            }
            if (str_ends_with($name, '/')) {
                @mkdir($tmp . '/' . $name, 0755, true);
                continue;
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $content = (string)stream_get_contents($stream);
            fclose($stream);
            $total += strlen($content);
            if ($total > 20 * 1024 * 1024) {
                self::removeDir($tmp);
                $zip->close();
                return ['ok' => false, 'msg' => '解压后超过 20MB，已拒绝', 'slug' => ''];
            }
            $target = $tmp . '/' . $name;
            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($target, $content);
        }
        $zip->close();

        $base = null;
        if (is_file($tmp . '/' . $manifestName)) {
            $base = $tmp;
        } else {
            foreach (glob($tmp . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (is_file($dir . '/' . $manifestName)) {
                    $base = $dir;
                    break;
                }
            }
        }
        if ($base === null) {
            self::removeDir($tmp);
            return ['ok' => false, 'msg' => '安装包缺少 ' . $manifestName, 'slug' => ''];
        }

        $meta = json_decode((string)@file_get_contents($base . '/' . $manifestName), true);
        if (!is_array($meta)) {
            self::removeDir($tmp);
            return ['ok' => false, 'msg' => $manifestName . ' 不是合法 JSON', 'slug' => ''];
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($meta['slug'] ?? basename($base))) ?? '');
        if ($slug === '' || !preg_match('/^[a-z0-9_\-]+$/', $slug)) {
            self::removeDir($tmp);
            return ['ok' => false, 'msg' => '清单里的 slug 不合法', 'slug' => ''];
        }
        if ($kind === 'plugin') {
            $entry = (string)($meta['entry'] ?? 'plugin.php');
            if ($entry !== '' && !is_file($base . '/' . $entry)) {
                self::removeDir($tmp);
                return ['ok' => false, 'msg' => '插件入口文件缺失：' . $entry, 'slug' => ''];
            }
        }

        $target = $targetRoot . '/' . $slug;
        $backup = '';
        if (is_dir($target)) {
            if (!$replace) {
                self::removeDir($tmp);
                return ['ok' => false, 'msg' => '已存在同名' . ($kind === 'theme' ? '主题' : '插件') . '，请先卸载再安装', 'slug' => $slug];
            }
            $backup = $target . '.old-' . time();
            if (!@rename($target, $backup)) {
                self::removeDir($tmp);
                return ['ok' => false, 'msg' => '无法替换旧版本文件，请检查目录权限', 'slug' => $slug];
            }
        }

        if (!@rename($base, $target)) {
            @mkdir($target, 0755, true);
            self::copyDir($base, $target);
        }
        if ($backup !== '') {
            self::removeDir($backup);
        }
        self::removeDir($tmp);

        $action = $replace ? '已更新' : '已安装';
        return ['ok' => true, 'msg' => $action . '「' . (string)($meta['name'] ?? $slug) . '」到 v' . (string)($meta['version'] ?? '1.0.0'), 'slug' => $slug];
    }

    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    private static function copyDir(string $from, string $to): void
    {
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;
            if (is_dir($src)) {
                @mkdir($dst, 0755, true);
                self::copyDir($src, $dst);
            } else {
                @copy($src, $dst);
            }
        }
    }
}