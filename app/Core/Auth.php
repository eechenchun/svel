<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Auth.php
 * @desc 后台认证与登录限流：同一 IP 连续失败 5 次锁 10 分钟，计数落文件不占表
 */

namespace Svel\Core;

use Svel\Models\User;

final class Auth
{
    private const MAX_FAILS = 5;
    private const LOCK_SECONDS = 600;
    private const IDLE_SECONDS = 7200;
    private const ABSOLUTE_SECONDS = 43200;

    public static function start(): void
    {
        Csrf::startSession();
    }

    public static function user(): ?array
    {
        self::start();
        $uid = (int)($_SESSION['uid'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $now = time();
        $last = (int)($_SESSION['_svel_last'] ?? $now);
        $login = (int)($_SESSION['_svel_login_at'] ?? $now);
        if ($now - $last > self::IDLE_SECONDS || $now - $login > self::ABSOLUTE_SECONDS) {
            self::logout();
            return null;
        }
        $_SESSION['_svel_last'] = $now;
        return User::findById($uid);
    }

    /** 未登录直接跳登录页，返回当前用户 */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            Response::redirect(url('admin/login'));
        }
        return $user;
    }

    /** @return array{ok:bool,msg:string} */
    public static function attempt(string $username, string $password, string $ip = ''): array
    {
        $user = User::findByUsername($username);
        // 以账号为主键计数，避免伪造 X-Forwarded-For 轮换 IP 绕过限速
        $key = 'u:' . ($user !== null ? (string)$user['id'] : 'unknown');
        if ((bool)Config::get('login_limit_by_ip', false)) {
            $key .= ':' . md5($ip);
        }

        $state = self::readState($key);
        if ((int)$state['until'] > time()) {
            $left = (int)ceil(((int)$state['until'] - time()) / 60);
            return ['ok' => false, 'msg' => '尝试过于频繁，请 ' . max(1, $left) . ' 分钟后再试'];
        }

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            $state['fails'] = (int)$state['fails'] + 1;
            if ($state['fails'] >= self::MAX_FAILS) {
                $state['until'] = time() + self::LOCK_SECONDS;
                $state['fails'] = 0;
            }
            self::writeState($key, $state);
            return ['ok' => false, 'msg' => '账号或密码不正确'];
        }

        self::writeState($key, ['fails' => 0, 'until' => 0]);

        if (TwoFactor::enabled($user)) {
            self::start();
            session_regenerate_id(true);
            $_SESSION['_svel_2fa_uid'] = (int)$user['id'];
            $_SESSION['_svel_2fa_at'] = time();
            $_SESSION['_svel_2fa_fails'] = 0;
            Audit::log('login_2fa_pending', (string)$user['username'], '密码验证通过，等待 TOTP', (int)$user['id']);
            return ['ok' => true, 'msg' => '', 'need2fa' => true, 'user_id' => (int)$user['id']];
        }

        self::loginUser($user);
        return ['ok' => true, 'msg' => '', 'need2fa' => false, 'user_id' => (int)$user['id']];
    }

    /** 密码已通过后真正建立后台会话 */
    public static function loginUser(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        unset($_SESSION['_svel_2fa_uid'], $_SESSION['_svel_2fa_at'], $_SESSION['_svel_2fa_fails']);
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['_svel_last'] = time();
        $_SESSION['_svel_login_at'] = time();
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    private static function stateFile(string $key): string
    {
        $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        $cache = rtrim(str_replace('\\', '/', $dir), '/') . '/cache';
        if (!is_dir($cache)) {
            @mkdir($cache, 0755, true);
        }
        return $cache . '/login-' . md5($key) . '.json';
    }

    /** @return array{fails:int,until:int} */
    private static function readState(string $key): array
    {
        $file = self::stateFile($key);
        if (!is_file($file)) {
            return ['fails' => 0, 'until' => 0];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return [
            'fails' => (int)($data['fails'] ?? 0),
            'until' => (int)($data['until'] ?? 0),
        ];
    }

    private static function writeState(string $key, array $state): void
    {
        @file_put_contents(self::stateFile($key), json_encode($state), LOCK_EX);
    }
}