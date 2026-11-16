<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/NavController.php
 * @desc 导航管理：标题、链接、排序、显示与隐藏、新窗口打开
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Request;
use Svel\Models\Nav;

final class NavController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        Nav::seedDefault();
        Admin::render('导航', 'nav', ['rows' => Nav::all()], 'nav');
    }

    public function save(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        $title = trim((string)$req->input('title', ''));
        $link = trim((string)$req->input('url', ''));
        if ($title === '' || $link === '') {
            Admin::back(admin_url('nav'), 'error', '导航标题与链接都不能为空');
        }
        if (mb_strlen($title) > 40) {
            Admin::back(admin_url('nav'), 'error', '导航标题不超过 40 个字');
        }
        if (!preg_match('#^(https?://|/|\#)#i', $link)) {
            Admin::back(admin_url('nav'), 'error', '链接要以 http(s)://、/ 或 # 开头');
        }

        $data = [
            'title'  => $title,
            'url'    => $link,
            'sort'   => (int)$req->input('sort', 0),
            'status' => (string)$req->input('status', '1') === '0' ? 0 : 1,
            'target' => (string)$req->input('target', '_self') === '_blank' ? '_blank' : '_self',
        ];

        $db = \Svel\Core\Db::inst();
        if ($id > 0) {
            if (Nav::find($id) === null) {
                Admin::back(admin_url('nav'), 'error', '这条导航不存在');
            }
            $db->update('nav', $data, $db->qi('id') . ' = ?', [$id]);
        } else {
            $data['created_at'] = time();
            $db->insert('nav', $data);
        }

        Cache::flush();
        Admin::back(admin_url('nav'), 'ok', '导航已保存');
    }

    public function delete(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        if ($id <= 0 || Nav::find($id) === null) {
            Admin::back(admin_url('nav'), 'error', '导航不存在');
        }
        $db = \Svel\Core\Db::inst();
        $db->delete('nav', $db->qi('id') . ' = ?', [$id]);
        Cache::flush();
        Admin::back(admin_url('nav'), 'ok', '导航已删除');
    }
}