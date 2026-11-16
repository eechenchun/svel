<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/ThemeController.php
 * @desc 主题列表与切换；切换后清空页面缓存
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Request;
use Svel\Core\Theme;

final class ThemeController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        Admin::render('主题', 'themes', [
            'rows'   => Theme::all(),
            'active' => Theme::active(),
        ], 'theme');
    }

    public function activate(Request $req, array $params = []): void
    {
        Admin::guard();
        $slug = (string)$req->input('slug', '');
        if (!Theme::activate($slug)) {
            Admin::back(admin_url('theme'), 'error', '主题不存在或缺少 theme.json');
        }
        Admin::back(admin_url('theme'), 'ok', '已切换到「' . $slug . '」，前台缓存已清空');
    }
}