<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/SettingController.php
 * @desc 站点设置：基本、全局、SEO、附件、伪静态、后台六个选项卡
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Config;
use Svel\Core\Installer;
use Svel\Core\Request;
use Svel\Models\Option;
use Svel\Models\User;

final class SettingController
{
    private const TABS = ['basic', 'global', 'seo', 'upload', 'rewrite', 'admin'];

    private const UPLOAD_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];

    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        $tab = (string)$req->query('tab', 'basic');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'basic';
        }

        $dir = SVEL_ROOT . '/storage/rewrite';

        Admin::render('设置', 'settings', [
            'tab' => $tab,
            'opt' => [
                'title'          => (string)option('site.title', ''),
                'tagline'        => (string)option('site.tagline', ''),
                'description'    => (string)option('site.description', ''),
                'keywords'       => (string)option('site.keywords', ''),
                'icp'            => (string)option('site.icp', ''),
                'footer_text'    => (string)option('site.footer_text', ''),
                'per_page'       => (string)option('site.posts_per_page', '10'),
                'cache_ttl'      => (string)option('cache.ttl', '3600'),
                'timezone'       => (string)option('site.timezone', (string)Config::get('site.timezone', 'Asia/Shanghai')),
                'default_image'  => (string)option('seo.default_image', ''),
                'robots_extra'   => (string)option('seo.robots_extra', ''),
                'head_code'      => (string)option('seo.head_code', ''),
                'upload_max_mb'  => (string)option('upload.max_mb', '5'),
                'upload_exts'    => (string)option('upload.exts', 'jpg,jpeg,png,gif,webp,ico'),
                'admin_per_page' => (string)option('admin.per_page', '20'),
            ],
            'timezones' => [
                'Asia/Shanghai'    => '中国标准时间（UTC+8）',
                'Asia/Hong_Kong'   => '香港（UTC+8）',
                'Asia/Taipei'      => '台北（UTC+8）',
                'Asia/Tokyo'       => '东京（UTC+9）',
                'Asia/Singapore'   => '新加坡（UTC+8）',
                'UTC'              => '协调世界时 UTC',
                'Europe/London'    => '伦敦（UTC+0/+1）',
                'America/New_York' => '纽约（UTC-5/-4）',
                'America/Los_Angeles' => '洛杉矶（UTC-8/-7）',
            ],
            'rewrite' => [
                'mode'   => (int)Config::get('url_mode', 1),
                'base'   => (string)Config::get('site.base', '/'),
                'apache' => $this->read($dir . '/apache.txt'),
                'nginx'  => $this->read($dir . '/nginx.conf'),
                'caddy'  => $this->read($dir . '/caddy.txt'),
                'iis'    => $this->read($dir . '/iis.txt'),
            ],
        ], 'setting');
    }

    public function save(Request $req, array $params = []): void
    {
        $user = Admin::guard();
        $tab = (string)$req->input('tab', 'basic');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'basic';
        }
        $back = admin_url('setting?tab=' . $tab);
        $section = (string)$req->input('section', 'site');

        if ($section === 'account') {
            $this->saveAccount($req, $user);
            return;
        }

        if ($section === 'global') {
            $perPage = (int)$req->input('per_page', 10);
            $perPage = max(1, min(50, $perPage));
            $ttl = (int)$req->input('cache_ttl', 3600);
            $ttl = max(0, min(86400, $ttl));
            $tz = trim((string)$req->input('timezone', 'Asia/Shanghai'));
            try {
                new \DateTimeZone($tz);
            } catch (\Throwable) {
                Admin::back($back, 'error', '时区无效：' . $tz);
            }

            Option::set('site.posts_per_page', (string)$perPage);
            Option::set('cache.ttl', (string)$ttl);
            Option::set('site.timezone', $tz);

            Cache::flush();
            Admin::back($back, 'ok', '全局设置已保存，前台已刷新缓存');
        }

        if ($section === 'seo') {
            $extra = trim((string)$req->input('robots_extra', ''));
            if (mb_strlen($extra) > 2000) {
                Admin::back($back, 'error', 'robots 追加规则不超过 2000 字');
            }
            $head = trim((string)$req->input('head_code', ''));
            if (mb_strlen($head) > 5000) {
                Admin::back($back, 'error', 'head 代码不超过 5000 字');
            }

            Option::set('seo.default_image', trim((string)$req->input('default_image', '')));
            Option::set('seo.robots_extra', $extra);
            Option::set('seo.head_code', $head);

            Cache::flush();
            Admin::back($back, 'ok', 'SEO 设置已保存，前台已刷新缓存');
        }

        if ($section === 'upload') {
            $max = (int)$req->input('upload_max_mb', 5);
            $max = max(1, min(20, $max));
            $exts = [];
            foreach (explode(',', strtolower((string)$req->input('upload_exts', ''))) as $ext) {
                $ext = trim($ext, " \t\n\r\0\x0B.");
                if (in_array($ext, self::UPLOAD_EXTS, true)) {
                    $exts[] = $ext;
                }
            }
            $exts = array_values(array_unique($exts));
            if ($exts === []) {
                Admin::back($back, 'error', '至少保留一种图片格式：' . implode(' / ', self::UPLOAD_EXTS));
            }

            Option::set('upload.max_mb', (string)$max);
            Option::set('upload.exts', implode(',', $exts));

            Admin::back($back, 'ok', '附件设置已保存');
        }

        if ($section === 'admin') {
            $per = (int)$req->input('admin_per_page', 20);
            $per = max(5, min(100, $per));
            Option::set('admin.per_page', (string)$per);

            Admin::back($back, 'ok', '后台设置已保存');
        }

        // 基本设置
        $title = trim((string)$req->input('title', ''));
        if ($title === '') {
            Admin::back($back, 'error', '站点名称不能为空');
        }

        Option::set('site.title', $title);
        Option::set('site.tagline', trim((string)$req->input('tagline', '')));
        Option::set('site.description', trim((string)$req->input('description', '')));
        Option::set('site.keywords', trim((string)$req->input('keywords', '')));
        Option::set('site.icp', trim((string)$req->input('icp', '')));
        Option::set('site.footer_text', trim((string)$req->input('footer_text', '')));

        Cache::flush();
        Admin::back($back, 'ok', '基本设置已保存，前台已刷新缓存');
    }

    /** 伪静态：重新检测或手动切档，规则文件同步重写 */
    public function rewrite(Request $req, array $params = []): void
    {
        Admin::guard();
        $manual = (string)$req->input('mode', 'auto');
        if (in_array($manual, ['1', '2', '3'], true)) {
            $mode = (int)$manual;
            $note = '已手动切到 L' . $mode;
        } else {
            $result = Installer::detectUrlMode(abs_url(''));
            $mode = (int)$result['mode'];
            $note = (string)$result['note'];
        }

        Config::set('url_mode', $mode);
        Installer::saveConfig(Config::all());
        Installer::writeRewrite($mode, (string)Config::get('site.base', '/'));
        Cache::flush();

        Admin::back(admin_url('setting?tab=rewrite'), 'ok', '当前 URL 档位：L' . $mode . '（' . $note . '）');
    }

    private function saveAccount(Request $req, array $user): void
    {
        $back = admin_url('setting?tab=admin');
        $nickname = trim((string)$req->input('nickname', ''));
        $email = trim((string)$req->input('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Admin::back($back, 'error', '邮箱格式不正确');
        }

        User::updateProfile((int)$user['id'], ['nickname' => $nickname, 'email' => $email]);

        $password = (string)$req->input('password', '');
        $password2 = (string)$req->input('password2', '');
        if ($password !== '' || $password2 !== '') {
            $current = (string)$req->input('current_password', '');
            if ($current === '' || !password_verify($current, (string)($user['password_hash'] ?? ''))) {
                Admin::back($back, 'error', '请输入当前密码，验证通过后才能修改新密码');
            }
            if (mb_strlen($password) < 10) {
                Admin::back($back, 'error', '新密码至少 10 位');
            }
            if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                Admin::back($back, 'error', '新密码必须同时包含字母和数字');
            }
            if ($password !== $password2) {
                Admin::back($back, 'error', '两次输入的新密码不一致');
            }
            User::updatePassword((int)$user['id'], $password);
            \Svel\Core\Auth::start();
            session_regenerate_id(true);
        }

        Admin::back($back, 'ok', '账号信息已更新');
    }

    private function read(string $file): string
    {
        if (!is_file($file)) {
            return '';
        }
        return (string)@file_get_contents($file);
    }
}