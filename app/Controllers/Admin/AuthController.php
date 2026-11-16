<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/AuthController.php
 * @desc 后台登录、退出
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Audit;
use Svel\Core\Auth;
use Svel\Core\Csrf;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\View;

final class AuthController
{
    public function form(Request $req, array $params = []): void
    {
        if (Auth::user() !== null) {
            Response::redirect(admin_url(''));
        }
        echo View::admin('login', ['error' => '', 'username' => '']);
    }

    public function login(Request $req, array $params = []): void
    {
        Csrf::assert();
        $username = trim((string)$req->input('username', ''));
        $password = (string)$req->input('password', '');

        if ($username === '' || $password === '') {
            echo View::admin('login', ['error' => '请填写账号与密码', 'username' => $username]);
            return;
        }

        $result = Auth::attempt($username, $password, $req->ip());
        if (!$result['ok']) {
            Audit::log('login_failed', $username, '后台密码验证失败');
            echo View::admin('login', ['error' => $result['msg'], 'username' => $username]);
            return;
        }

        if (!empty($result['need2fa'])) {
            Response::redirect(admin_url('2fa'));
        }

        Audit::log('login', $username, '后台登录成功', (int)$result['user_id']);
        Response::redirect(admin_url(''));
    }

    public function logout(Request $req, array $params = []): void
    {
        $user = Auth::user();
        if ($user !== null) {
            Audit::log('logout', (string)$user['username'], '主动退出', (int)$user['id']);
        }
        Auth::logout();
        Response::redirect(admin_url('login'));
    }
}