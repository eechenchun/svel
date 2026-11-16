<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Response.php
 * @desc 响应状态、响应头与直接输出。控制器只写正文，头部统一在此登记
 */

namespace Svel\Core;

final class Response
{
    private static int $status = 200;
    private static array $headers = [];
    private static bool $sent = false;

    public static function status(int $code): void
    {
        self::$status = $code;
    }

    public static function getStatus(): int
    {
        return self::$status;
    }

    public static function header(string $name, string $value): void
    {
        self::$headers[$name] = $value;
    }

    public static function headers(): array
    {
        return self::$headers;
    }

    public static function reset(): void
    {
        self::$status = 200;
        self::$headers = [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'SAMEORIGIN',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'",
        ];
        self::$sent = false;
    }

    public static function redirect(string $url, int $code = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $code);
        }
        exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, string $type = 'text/plain; charset=utf-8', int $code = 200): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: ' . $type);
        }
        echo $body;
        exit;
    }

    /** 输出响应头（正文由调用方 echo） */
    public static function sendHeaders(): void
    {
        if (self::$sent || headers_sent()) {
            self::$sent = true;
            return;
        }
        http_response_code(self::$status);
        foreach (self::$headers as $k => $v) {
            header($k . ': ' . $v);
        }
        self::$sent = true;
    }
}