<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Plugin.php
 * @desc 插件清单：以 plugins/ 目录下的 plugin.json 为准，数据库只存启用状态
 */

namespace Svel\Models;

use Svel\Core\Cache;
use Svel\Core\Db;
use Svel\Core\Hook;

final class Plugin
{
    public static function all(): array
    {
        $db = Db::inst();
        return $db->all('SELECT * FROM ' . $db->qi($db->table('plugin')) . ' ORDER BY ' . $db->qi('slug') . ' ASC');
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('plugin')) . ' WHERE ' . $db->qi('slug') . ' = ? LIMIT 1', [$slug]);
    }

    /** 启用中的插件，附带入口文件名（从 plugin.json 读，不存表） */
    public static function enabledWithHooks(): array
    {
        $db = Db::inst();
        $rows = $db->all('SELECT * FROM ' . $db->qi($db->table('plugin')) . ' WHERE ' . $db->qi('status') . ' = 1');
        foreach ($rows as &$row) {
            $manifest = self::manifest((string)$row['slug']);
            $row['entry'] = (string)($manifest['entry'] ?? 'plugin.php');
        }
        unset($row);
        return $rows;
    }

    public static function manifest(string $slug): array
    {
        $file = SVEL_ROOT . '/plugins/' . basename($slug) . '/plugin.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** 扫描 plugins/ 目录，把新插件登记进库；已有插件只同步版本与钩子声明 */
    public static function discover(): int
    {
        $db = Db::inst();
        $added = 0;
        foreach (glob(SVEL_ROOT . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $manifest = self::manifest($slug);
            if ($manifest === []) {
                continue;
            }
            $row = self::findBySlug($slug);
            $data = [
                'name'     => (string)($manifest['name'] ?? $slug),
                'version'  => (string)($manifest['version'] ?? '1.0.0'),
                'hooks'    => json_encode($manifest['hooks'] ?? [], JSON_UNESCAPED_UNICODE),
                'priority' => (int)($manifest['priority'] ?? 10),
            ];
            if ($row === null) {
                $db->insert('plugin', $data + [
                    'slug'         => $slug,
                    'status'       => !empty($manifest['default']) ? 1 : 0,
                    'source'       => 'upload',
                    'checksum'     => '',
                    'installed_at' => time(),
                ]);
                $added++;
            } else {
                $db->update('plugin', $data, $db->qi('slug') . ' = ?', [$slug]);
            }
        }
        Hook::rebuild();
        Cache::flush();
        return $added;
    }

    public static function toggle(string $slug): bool
    {
        $row = self::findBySlug($slug);
        if ($row === null) {
            return false;
        }
        $db = Db::inst();
        $new = (int)$row['status'] === 1 ? 0 : 1;
        $db->update('plugin', ['status' => $new], $db->qi('slug') . ' = ?', [$slug]);
        Hook::rebuild();
        Cache::flush();
        return $new === 1;
    }

    public static function remove(string $slug): void
    {
        $db = Db::inst();
        $db->delete('plugin', $db->qi('slug') . ' = ?', [$slug]);
        Hook::rebuild();
        Cache::flush();
    }

    /** 一次聚合查询统计启用数量 */
    public static function enabledCount(): int
    {
        $db = Db::inst();
        return (int)$db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('plugin')) . ' WHERE ' . $db->qi('status') . ' = 1', [], 0);
    }

    /** 解析插件声明的编辑器钩子，后台用来说明由谁接管编辑区 */
    public static function editorProvider(): ?array
    {
        foreach (self::enabledWithHooks() as $row) {
            $hooks = json_decode((string)$row['hooks'], true);
            $names = is_array($hooks) ? (array_is_list($hooks) ? $hooks : array_keys($hooks)) : [];
            if (in_array('admin_editor', $names, true)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 安装插件 zip：交给 Core\Package 做路径穿越、体积与覆盖检查
     * @return array{ok:bool,msg:string,slug:string}
     */
    public static function installZip(string $zipFile, bool $replace = false): array
    {
        $result = \Svel\Core\Package::install($zipFile, 'plugin', SVEL_ROOT . '/plugins', 'plugin.json', $replace);
        if ($result['ok']) {
            self::discover();
        }
        return $result;
    }

    /** 卸载插件：启用中的不允许卸载，目录、表记录与钩子缓存一并清理 */
    public static function uninstall(string $slug): array
    {
        $row = self::findBySlug($slug);
        if ($row === null) {
            return ['ok' => false, 'msg' => '插件不存在'];
        }
        if ((int)$row['status'] === 1) {
            return ['ok' => false, 'msg' => '插件正在启用中，请先停用再卸载'];
        }

        $dir = SVEL_ROOT . '/plugins/' . basename($slug);
        if (is_dir($dir)) {
            \Svel\Core\Package::removeDir($dir);
        }
        self::remove($slug);
        Cache::flush();

        return ['ok' => true, 'msg' => '插件已卸载，文件与记录均已删除'];
    }
}
