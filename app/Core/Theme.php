<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Theme.php
 * @desc 主题：以 themes/ 目录为准，theme 表只记录启用状态；缺目录自动回落 default
 */

namespace Svel\Core;

use Svel\Models\Option;

final class Theme
{
    public static function root(): string
    {
        return SVEL_ROOT . '/themes';
    }

    /** @return array<int,array{slug:string,name:string,version:string,status:int,dir:string}> */
    public static function all(): array
    {
        $rows = [];
        foreach (glob(self::root() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $meta = json_decode((string)@file_get_contents($dir . '/theme.json'), true);
            if (!is_array($meta)) {
                continue;
            }
            $rows[] = [
                'slug'    => $slug,
                'name'    => (string)($meta['name'] ?? $slug),
                'version' => (string)($meta['version'] ?? '1.0.0'),
                'status'  => $slug === self::active() ? 1 : 0,
                'dir'     => $dir,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['slug'], $b['slug']));
        return $rows;
    }

    public static function active(): string
    {
        $slug = (string)option('site.theme', 'default');
        if ($slug === '' || !is_dir(self::root() . '/' . basename($slug))) {
            return 'default';
        }
        return basename($slug);
    }

    public static function activate(string $slug): bool
    {
        $slug = basename($slug);
        if (!is_dir(self::root() . '/' . $slug) || !is_file(self::root() . '/' . $slug . '/theme.json')) {
            return false;
        }
        Option::set('site.theme', $slug);

        $db = Db::inst();
        $db->exec('UPDATE ' . $db->qi($db->table('theme')) . ' SET ' . $db->qi('status') . ' = 0');
        $exists = $db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('theme')) . ' WHERE ' . $db->qi('slug') . ' = ?', [$slug], 0);
        if ((int)$exists > 0) {
            $db->update('theme', ['status' => 1], $db->qi('slug') . ' = ?', [$slug]);
        } else {
            $meta = json_decode((string)@file_get_contents(self::root() . '/' . $slug . '/theme.json'), true);
            $db->insert('theme', [
                'slug'         => $slug,
                'name'         => (string)($meta['name'] ?? $slug),
                'version'      => (string)($meta['version'] ?? '1.0.0'),
                'status'       => 1,
                'installed_at' => time(),
            ]);
        }

        Cache::flush();
        return true;
    }

    /** 从 zip 安装主题，安装后不自动启用 */
    public static function installZip(string $zipFile, bool $replace = false): array
    {
        $result = Package::install($zipFile, 'theme', self::root(), 'theme.json', $replace);
        if (!$result['ok']) {
            return $result;
        }
        $slug = $result['slug'];
        $meta = json_decode((string)@file_get_contents(self::root() . '/' . $slug . '/theme.json'), true) ?: [];
        $db = Db::inst();
        $exists = (int)$db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('theme')) . ' WHERE ' . $db->qi('slug') . ' = ?', [$slug], 0);
        if ($replace && $exists > 0) {
            $db->update('theme', [
                'name'    => (string)($meta['name'] ?? $slug),
                'version' => (string)($meta['version'] ?? '1.0.0'),
            ], $db->qi('slug') . ' = ?', [$slug]);
        } elseif ($exists === 0) {
            $db->insert('theme', [
                'slug'         => $slug,
                'name'         => (string)($meta['name'] ?? $slug),
                'version'      => (string)($meta['version'] ?? '1.0.0'),
                'status'       => 0,
                'installed_at' => time(),
            ]);
        }
        Cache::flush();
        return $result;
    }

    /** 卸载主题：启用中的主题不允许卸载，文件与表记录一起删除 */
    public static function uninstall(string $slug): array
    {
        $slug = basename($slug);
        if ($slug === self::active()) {
            return ['ok' => false, 'msg' => '这是当前启用的主题，请先切换到其他主题再卸载'];
        }
        $dir = self::root() . '/' . $slug;
        if (!is_dir($dir)) {
            return ['ok' => false, 'msg' => '主题目录不存在'];
        }

        Package::removeDir($dir);
        $db = Db::inst();
        $db->delete('theme', $db->qi('slug') . ' = ?', [$slug]);
        Cache::flush();

        return ['ok' => true, 'msg' => '主题已卸载，文件与记录均已删除'];
    }
}
