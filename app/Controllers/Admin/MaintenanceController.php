<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/MaintenanceController.php
 * @desc 后台手动执行会话/日志/备份清理
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Audit;
use Svel\Core\Maintenance;
use Svel\Core\Request;

final class MaintenanceController
{
    public function run(Request $req, array $params = []): void
    {
        Admin::guard();
        $stats = Maintenance::run();
        Audit::log('maintenance', 'cleanup', json_encode($stats, JSON_UNESCAPED_UNICODE) ?: '');
        Admin::back(
            admin_url('audit'),
            'ok',
            '清理完成：会话 ' . $stats['sessions'] . '、日志 ' . $stats['logs'] . '、备份 ' . $stats['backups'] . '、缓存 ' . $stats['cache']
        );
    }
}