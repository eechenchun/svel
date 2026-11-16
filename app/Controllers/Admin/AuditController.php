<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/AuditController.php
 * @desc 后台操作审计列表
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Db;
use Svel\Core\Request;

final class AuditController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        $db = Db::inst();
        $rows = $db->all(
            'SELECT a.*, u.' . $db->qi('username') . ' FROM ' . $db->qi($db->table('audit')) . ' a'
            . ' LEFT JOIN ' . $db->qi($db->table('user')) . ' u ON u.' . $db->qi('id') . ' = a.' . $db->qi('user_id')
            . ' ORDER BY a.' . $db->qi('id') . ' DESC LIMIT 300'
        );
        Admin::render('审计日志', 'audit', ['rows' => $rows], 'audit');
    }
}