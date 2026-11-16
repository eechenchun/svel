<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/DashboardController.php
 * @desc 后台仪表盘：内容概况 + 运行环境，出问题能直接看到该看的地方
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Config;
use Svel\Core\Db;
use Svel\Core\Log;
use Svel\Core\Request;
use Svel\Core\Theme;
use Svel\Models\Plugin;
use Svel\Models\Post;

final class DashboardController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        // 开启自动升级时，后台加载会顺带检查并升级，带 6 小时间隔与文件锁
        if (\Svel\Core\Upgrader::autoEnabled()) {
            try {
                $auto = \Svel\Core\Upgrader::maybeAuto();
                if (is_array($auto) && $auto['ok'] && $auto['upgraded']) {
                    \Svel\Core\Flash::set('ok', '已自动升级到 v' . $auto['version']);
                }
            } catch (Throwable $e) {
                \Svel\Core\Log::warn('自动升级异常：' . $e->getMessage());
            }
        }

        $db = Db::inst();
        $counts = [
            'published' => (int)$db->val(
                'SELECT COUNT(*) FROM ' . $db->qi($db->table('post')) . ' WHERE ' . $db->qi('status') . ' = ? AND ' . $db->qi('type') . ' = ?',
                ['publish', 'post'],
                0
            ),
            'draft'     => (int)$db->val(
                'SELECT COUNT(*) FROM ' . $db->qi($db->table('post')) . ' WHERE ' . $db->qi('status') . ' = ? AND ' . $db->qi('type') . ' = ?',
                ['draft', 'post'],
                0
            ),
            'category'  => (int)$db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('category')), [], 0),
            'tag'       => (int)$db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('tag')), [], 0),
        ];

        $env = [
            'PHP 版本'   => PHP_VERSION,
            '数据库'     => $db->driver() === 'sqlite' ? 'SQLite' : 'MySQL',
            '表前缀'     => $db->prefix(),
            'URL 档位'   => 'L' . (int)Config::get('url_mode', 1),
            '当前主题'   => Theme::active(),
            '启用插件'   => (string)Plugin::enabledCount() . ' 个',
            '缓存 TTL'   => (int)option('cache.ttl', 3600) . ' 秒',
            '数据目录'   => (string)Config::get('storage.dir', ''),
            '日志文件'   => (string)(Log::latest() ?? '暂无'),
        ];

        Admin::render('仪表盘', 'dashboard', [
            'counts'  => $counts,
            'recent'  => Post::recent(5),
            'env'     => $env,
            'version' => SVEL_VERSION,
        ], 'dash');
    }
}