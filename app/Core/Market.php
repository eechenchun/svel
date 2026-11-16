<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Market.php
 * @desc 市场客户端：拉取清单（带本地缓存）、下载安装包并校验 SHA-256
 */

namespace Svel\Core;

final class Market
{
    private static ?array $items = null;

    public static function baseUrl(): string
    {
        return rtrim((string)Config::get('market_url', ''), '/');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function items(bool $force = false): array
    {
        if (self::$items !== null && !$force) {
            return self::$items;
        }

        $base = self::baseUrl();
        if ($base === '') {
            return self::$items = [];
        }

        $cacheFile = self::cacheFile();
        if (!$force && is_file($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && (int)($cached['expire'] ?? 0) > time() && is_array($cached['items'] ?? null)) {
                return self::$items = $cached['items'];
            }
        }

        $body = self::httpGet($base . '/api/items');
        if ($body === null) {
            // 拉不到就用过期缓存顶上，避免后台整页失败
            if (is_file($cacheFile)) {
                $cached = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($cached) && is_array($cached['items'] ?? null)) {
                    return self::$items = $cached['items'];
                }
            }
            return self::$items = [];
        }

        $data = json_decode($body, true);
        $items = is_array($data) && !empty($data['ok']) && is_array($data['items'] ?? null) ? $data['items'] : [];
        self::writeCache($items);
        return self::$items = $items;
    }

    public static function find(string $type, string $slug): ?array
    {
        foreach (self::items() as $item) {
            if ((string)$item['type'] === $type && (string)$item['slug'] === $slug) {
                return $item;
            }
        }
        return null;
    }

    /** @return array{ok:bool,msg:string,file:string} */
    public static function download(array $item): array
    {
$url = (string)($item['download'] ?? '');
        $expected = strtolower((string)($item['checksum'] ?? ''));
        $signature = (string)($item['signature'] ?? '');
        if ($url === '') {
            return ['ok' => false, 'msg' => '清单里没有下载地址', 'file' => ''];
        }
        $base = self::baseUrl();
        if ($base === '' || !preg_match('#^https?://#i', $url) || strpos($url, $base . '/') !== 0) {
            return ['ok' => false, 'msg' => '安装包地址不在市场域名下，已中止', 'file' => ''];
        }

        $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        $tmpDir = rtrim(str_replace('\\', '/', $dir), '/') . '/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            return ['ok' => false, 'msg' => '临时目录不可写', 'file' => ''];
        }
        $file = $tmpDir . '/pkg-' . bin2hex(random_bytes(5)) . '.zip';

        if (!self::httpDownload($url, $file)) {
            return ['ok' => false, 'msg' => '安装包下载失败，请检查市场地址或网络', 'file' => ''];
        }
        if ((int)@filesize($file) > 20 * 1024 * 1024) {
            @unlink($file);
            return ['ok' => false, 'msg' => '安装包超过 20MB，已拒绝', 'file' => ''];
        }
if ($expected !== '') {
            $actual = hash_file('sha256', $file) ?: '';
            if (!hash_equals($expected, $actual)) {
                @unlink($file);
                return ['ok' => false, 'msg' => '安装包校验失败（SHA-256 不一致），已中止安装', 'file' => ''];
            }
        }
        if (!Signer::verifyFile($file, $signature)) {
            @unlink($file);
            return ['ok' => false, 'msg' => '安装包缺少有效签名，已中止安装，请刷新市场清单后重试', 'file' => ''];
        }

        return ['ok' => true, 'msg' => '', 'file' => $file];
    }

    private static function cacheFile(): string
    {
        $dir = (string)Config::get('storage.dir', SVEL_ROOT . '/storage');
        $cache = rtrim(str_replace('\\', '/', $dir), '/') . '/cache';
        if (!is_dir($cache)) {
            @mkdir($cache, 0755, true);
        }
        return $cache . '/market.json';
    }

    private static function writeCache(array $items): void
    {
        $file = self::cacheFile();
        $payload = json_encode(['expire' => time() + 300, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload !== false) {
            @file_put_contents($file, $payload, LOCK_EX);
        }
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => 'SvelCMS/' . SVEL_VERSION,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $code === 200) ? (string)$body : null;
        }

        $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }

    private static function httpDownload(string $url, string $target): bool
    {
        if (function_exists('curl_init')) {
            $fp = @fopen($target, 'wb');
            if ($fp === false) {
                return false;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'SvelCMS/' . SVEL_VERSION,
            ]);
            $ok = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            if ($ok === false || $code !== 200) {
                @unlink($target);
                return false;
            }
            return true;
        }

        $ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            return false;
        }
        return @file_put_contents($target, $body) !== false;
    }

    /**
     * 用市场账号换取 API 令牌
     * @return array{ok:bool,msg:string,token:string,username:string}
     */
    public static function login(string $username, string $password): array
    {
        $body = self::httpPost(self::baseUrl() . '/api/login', [
            'username' => $username,
            'password' => $password,
        ]);
        $data = $body === null ? null : json_decode($body, true);
        if (!is_array($data) || empty($data['ok'])) {
            return ['ok' => false, 'msg' => (string)($data['msg'] ?? '连不上市场，账号密码未验证'), 'token' => '', 'username' => ''];
        }
        return [
            'ok'       => true,
            'msg'      => '',
            'token'    => (string)($data['token'] ?? ''),
            'username' => (string)($data['user']['username'] ?? $username),
        ];
    }

    /**
     * 绑定当前站点到市场账号
     * @return array{ok:bool,msg:string,site_id:int,bind_token:string}
     */
    public static function bindSite(string $token, string $url, string $name): array
    {
        $body = self::httpPost(self::baseUrl() . '/api/site/bind', [
            'token' => $token,
            'url'   => $url,
            'name'  => $name,
        ]);
        $data = $body === null ? null : json_decode($body, true);
        if (!is_array($data) || empty($data['ok'])) {
            return ['ok' => false, 'msg' => (string)($data['msg'] ?? '绑定失败，请稍后重试'), 'site_id' => 0, 'bind_token' => ''];
        }
        return [
            'ok'         => true,
            'msg'        => (string)($data['msg'] ?? '绑定成功'),
            'site_id'    => (int)($data['site_id'] ?? 0),
            'bind_token' => (string)($data['bind_token'] ?? ''),
        ];
    }

    private static function httpPost(string $url, array $data): ?string
    {
        $payload = http_build_query($data);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'SvelCMS/' . SVEL_VERSION,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) ? $body : null;
        }
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded
",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }
}
