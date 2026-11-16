<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Router.php
 * @desc 路由：三档 URL 归一化 + 正则匹配，控制器统一签名 controller(Request, array $params)
 */

namespace Svel\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:string,3:string}> */
    private static array $routes = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }
        $routes = require $file;
        foreach ($routes as $r) {
            self::$routes[] = [
                strtoupper((string)$r[0]),
                (string)$r[1],
                (string)$r[2],
                self::compile((string)$r[1]),
            ];
        }
        self::$loaded = true;
    }

    private static function compile(string $pattern): string
    {
        $parts = preg_split('/\{(\w+)\}/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $re = '';
        foreach ((array)$parts as $i => $part) {
            $re .= ($i % 2 === 1) ? '([^/]+)' : preg_quote((string)$part, '#');
        }
        return '#^' . $re . '$#';
    }

    /** 把请求 URI 按当前档位还原成逻辑路径，如 /post/hello.html */
    public static function normalize(Request $req): string
    {
        $path = $req->rawPath;
        $base = rtrim((string)Config::get('site.base', '/'), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if ($path === '') {
            $path = '/';
        }

        $mode = (int)Config::get('url_mode', 1);
        if ($mode === 2 && str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
            if ($path === '') {
                $path = '/';
            }
        }
        if ($mode === 3) {
            $p = (string)($req->query['p'] ?? '');
            $path = '/' . ltrim($p, '/');
            if ($path === '/') {
                $path = '/';
            }
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    /**
     * @return array{0:string,1:string,2:array<string,string>}|null
     */
    public static function resolve(string $path, string $method): ?array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        foreach (self::$routes as $route) {
            [$m, $pattern, $target, $re] = $route;
            if ($m !== $method) {
                continue;
            }
            if (!preg_match($re, $path, $matches)) {
                continue;
            }
            array_shift($matches);
            preg_match_all('/\{(\w+)\}/', $pattern, $names);
            $params = [];
            foreach ($names[1] as $i => $name) {
                $params[$name] = $matches[$i] ?? '';
            }
            [$class, $action] = array_pad(explode('@', $target, 2), 2, 'index');
            return ['Svel\\Controllers\\' . $class, $action, $params];
        }
        return null;
    }

    public static function dispatch(Request $req): void
    {
        $path = self::normalize($req);
        $hit = self::resolve($path, $req->method);

        if ($hit === null) {
            $ctrl = new \Svel\Controllers\ErrorController();
            $ctrl->notFound($req, ['path' => $path]);
            return;
        }

        [$class, $action, $params] = $hit;
        if (!class_exists($class) || !method_exists($class, $action)) {
            throw new \RuntimeException('路由目标不存在：' . $class . '::' . $action);
        }
        $ctrl = new $class();
        $ctrl->{$action}($req, $params);
    }
}