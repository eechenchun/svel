<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Csrf.php
 * @desc CSRF 令牌：所有写操作校验，失败返回 419
 */

namespace Svel\Core;

final class Csrf
{
    private const KEY = '_svel_csrf';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $storage = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        $dir = rtrim(str_replace('\\', '/', $storage), '/') . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('svelsid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (string)(Config::get('site.base', '/') ?: '/'),
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        ]);
        @session_start();
    }

    public static function token(): string
    {
        self::startSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION[self::KEY];
    }

    public static function check(?string $token): bool
    {
        self::startSession();
        $saved = (string)($_SESSION[self::KEY] ?? '');
        return $saved !== '' && is_string($token) && hash_equals($saved, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    /** 写操作统一入口：令牌不对就 419 并终止 */
    public static function assert(): void
    {
        $token = (string)($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!self::check($token)) {
            if (!headers_sent()) {
                http_response_code(419);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<!doctype html><meta charset="utf-8"><title>419 页面已过期</title>'
                . '<h1>419 页面已过期</h1><p>表单令牌无效或已过期，请返回上一页刷新后重新提交。</p>';
            exit;
        }
    }
}