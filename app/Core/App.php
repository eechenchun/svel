<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/App.php
 * @desc 应用启动：安装态判断、运行时引导、页面缓存包装、异常兜底
 */

namespace Svel\Core;

use Svel\Models\Option;

final class App
{
    public static function run(): void
    {
        $req = Request::capture();
        Response::reset();

        try {
            if (!self::installed()) {
                self::beforeInstall($req);
                return;
            }
            Config::load(SVEL_ROOT . '/config.php');
            self::boot($req);
            self::handle($req);
        } catch (\Throwable $e) {
            self::fail($e);
        }
    }

    public static function installed(): bool
    {
        return is_file(SVEL_ROOT . '/config.php') && is_file(SVEL_ROOT . '/install.lock');
    }

    private static function beforeInstall(Request $req): void
    {
        if ($req->isAsset()) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        Response::redirect('/install.php');
    }

    private static function boot(Request $req): void
    {
        $debug = (bool)Config::get('debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');

        $storage = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        Log::init($storage . '/logs');

        Db::boot((array)Config::get('db', []));

        // 时区可由后台“设置 → 全局设置”覆盖，未设置时沿用 config.php
        $timezone = (string)option('site.timezone', (string)Config::get('site.timezone', 'Asia/Shanghai'));
        date_default_timezone_set($timezone);

        // 升级成功后由新代码补一次表结构（新版本新增的表/字段），完成后自动清除标记
        if ((string)option('schema_pending', '0') === '1') {
            try {
                $sync = Schema::sync();
                if ($sync['errors'] === []) {
                    Option::set('schema_pending', '0');
                } else {
                    Log::warn('升级补表结构有未完成项：' . implode('；', $sync['errors']));
                }
            } catch (\Throwable $e) {
                Log::warn('升级补表结构失败：' . $e->getMessage());
            }
        }

        $ttl = (int)option('cache.ttl', 3600);
        Cache::init($storage, $ttl);

        $theme = (string)option('site.theme', (string)Config::get('theme', 'default'));
        View::init(SVEL_ROOT, $theme);

        Router::load(SVEL_ROOT . '/app/routes.php');

        if (class_exists('\Svel\Core\Hook')) {
            Hook::boot();
        }
    }

    private static function handle(Request $req): void
    {
        $path = Router::normalize($req);
        $ttl = (int)option('cache.ttl', 3600);
        $cacheable = $req->method === 'GET'
            && $ttl > 0
            && $req->query === []
            && self::cacheablePath($path);

        $cacheKey = $path;

        if ($cacheable) {
            $hit = Cache::get($cacheKey);
            if ($hit !== null) {
                Response::status($hit['status']);
                foreach ($hit['headers'] as $k => $v) {
                    Response::header((string)$k, (string)$v);
                }
                Response::header('X-Svel-Cache', 'HIT');
                Response::sendHeaders();
                echo $hit['body'];
                return;
            }
        }

        Response::header('X-Svel-Cache', 'MISS');
        ob_start();
        Router::dispatch($req);
        $body = (string)ob_get_clean();

        Response::sendHeaders();
        echo $body;

        if ($cacheable) {
            $status = Response::getStatus();
            if ($status === 200) {
                $storeHeaders = [];
                foreach (Response::headers() as $k => $v) {
                    if (in_array(strtolower((string)$k), ['content-type', 'cache-control'], true)) {
                        $storeHeaders[$k] = $v;
                    }
                }
                Cache::put($cacheKey, $body, $status, $storeHeaders, $ttl);
            }
        }
        Cache::gc($ttl);
    }

    /** 只缓存白名单页面，避免任意 URL 与查询串撑大缓存 */
    private static function cacheablePath(string $path): bool
    {
        if ($path === '/' || $path === '/feed' || $path === '/robots.txt' || $path === '/sitemap.xml') {
            return true;
        }
        if (preg_match('#^/page/[1-9][0-9]*$#', $path)
            || preg_match('#^/sitemap-[1-9][0-9]*\.xml$#', $path)
            || preg_match('#^/sitemap-(pages|taxonomy)\.xml$#', $path)
            || preg_match('#^/post/[a-z0-9-]+\.html$#', $path)
            || preg_match('#^/[a-z0-9-]+\.html$#', $path)
        ) {
            return true;
        }
        return (bool)preg_match('#^/category/[a-z0-9-]+(?:/page/[1-9][0-9]*)?$#', $path)
            || (bool)preg_match('#^/tag/[a-z0-9-]+(?:/page/[1-9][0-9]*)?$#', $path);
    }

    private static function fail(\Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Log::error($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        $debug = false;
        try {
            $debug = (bool)Config::get('debug', false);
        } catch (\Throwable) {
            $debug = false;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><meta charset="utf-8"><title>500 服务器错误</title>';
        echo '<h1>500 服务器错误</h1>';
        echo $debug
            ? '<pre>' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>'
            : '<p>站点暂时无法访问，请稍后再试。详细原因已写入日志。</p>';
    }
}