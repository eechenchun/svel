<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Request.php
 * @desc 请求封装：只暴露业务需要的最小字段
 */

namespace Svel\Core;

final class Request
{
    public string $method;
    public string $rawPath;
    public array $query;
    public array $post;
    public array $server;

    public function __construct(string $method, string $rawPath, array $query, array $post, array $server)
    {
        $this->method = strtoupper($method);
        $this->rawPath = $rawPath;
        $this->query = $query;
        $this->post = $post;
        $this->server = $server;
    }

    public static function capture(): self
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        return new self(
            (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rawurldecode($path),
            $_GET,
            $_POST,
            $_SERVER
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return is_string($this->post[$key]) ? trim($this->post[$key]) : $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return is_string($this->query[$key]) ? trim($this->query[$key]) : $this->query[$key];
        }
        return $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        return (string)($this->server[$key] ?? '');
    }

    public function ip(): string
    {
        $remote = trim((string)($this->server['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            $remote = '0.0.0.0';
        }

        // 只有来自可信反代的请求才读取转发头，避免客户端自己伪造 X-Forwarded-For
        $trusted = (array)Config::get('trusted_proxies', []);
        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }

        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
            $v = (string)($this->server[$k] ?? '');
            if ($v !== '') {
                $first = trim(explode(',', $v)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $remote;
    }

    public function host(): string
    {
        return (string)($this->server['HTTP_HOST'] ?? '');
    }

    public function scheme(): string
    {
        $https = (string)($this->server['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }
        return strtolower((string)($this->server['HTTP_X_FORWARDED_PROTO'] ?? 'http')) === 'https' ? 'https' : 'http';
    }

    /** 静态资源请求不参与安装跳转，也不进页面缓存 */
    public function isAsset(): bool
    {
        return (bool)preg_match('/\.(css|js|png|jpe?g|gif|webp|ico|svg|woff2?|ttf|map|txt|xml)$/i', $this->rawPath);
    }
}