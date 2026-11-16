<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Admin.php
 * @desc 后台页面装配：统一鉴权、布局、提示与编辑器资源
 */

namespace Svel\Core;

final class Admin
{
    public static function render(string $title, string $template, array $data = [], string $active = ''): void
    {
        $user = Auth::require();

        $data['user'] = $user;
        $data['active'] = $active;
        $data['adminTitle'] = $title;
        $data['flash'] = Flash::take();

        $content = View::admin($template, $data);
        echo View::admin('layout', $data + ['content' => $content]);
    }

    /** 只读页面：未登录就跳登录页 */
    public static function require(): array
    {
        return Auth::require();
    }

    /** 写操作前置检查：登录 + CSRF，两处失败都不会执行后续逻辑 */
    public static function guard(): array
    {
$user = Auth::require();
        Csrf::assert();
        Audit::log('admin_write', (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), 'method=POST', (int)$user['id']);
        return $user;
    }

    public static function back(string $to, string $type, string $message): never
    {
        Flash::set($type, $message);
        Response::redirect($to);
    }
}