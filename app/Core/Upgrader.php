<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Upgrader.php
 * @desc 内核升级：检查版本、下载校验、备份、只替换内核文件、同步表结构，失败可回滚
 */

namespace Svel\Core;

use Svel\Models\Option;

final class Upgrader
{
    /** 同一请求内可能多次调用升级，记录需在内存里递进，避免 option 缓存读旧值 */
    private static ?array $history = null;

    /** 升级通道：stable 只取稳定版，beta 可拿到测试版 */
    public static function channel(): string
    {
        return (string)option('upgrade_channel', 'stable') === 'beta' ? 'beta' : 'stable';
    }

    /**
     * 最新版本信息。10 分钟内复用本地缓存，避免每次打开后台都等远端。
     * @return array{ok:bool,msg:string,version:string,channel:string,notes:string,package:string,checksum:string,size:int,published_at:int}|null
     */
    public static function latest(?string $channel = null, bool $force = false): ?array
    {
        $channel = $channel === 'beta' ? 'beta' : ($channel ?? self::channel());

        if (!$force) {
            $cache = json_decode((string)option('upgrade_latest', ''), true);
            if (is_array($cache)
                && (string)($cache['channel'] ?? '') === $channel
                && (int)($cache['time'] ?? 0) > time() - 600
                && is_array($cache['data'] ?? null)
            ) {
                return $cache['data'];
            }
        }

        $base = Market::baseUrl();
        if ($base === '') {
            return null;
        }
        $body = self::httpGet($base . '/api/cms/latest?channel=' . $channel);
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['ok']) || empty($data['version'])) {
            return null;
        }
        $info = [
            'ok'           => true,
            'msg'          => '',
            'version'      => (string)$data['version'],
            'channel'      => (string)($data['channel'] ?? 'stable'),
            'notes'        => (string)($data['notes'] ?? ''),
            'package'      => (string)($data['download'] ?? ''),
            'checksum'     => (string)($data['checksum'] ?? ''),
            'signature'    => (string)($data['signature'] ?? ''),
            'size'         => (int)($data['size'] ?? 0),
            'published_at' => (int)($data['published_at'] ?? 0),
        ];
        Option::set('upgrade_latest', json_encode([
            'time' => time(), 'channel' => $channel, 'data' => $info,
        ], JSON_UNESCAPED_UNICODE) ?: '');
        return $info;
    }

    /** @return array{ok:bool,msg:string,current:string,latest:string,has_update:bool,notes:string,size:int,channel:string,published_at:int} */
    public static function check(bool $force = false, ?string $channel = null): array
    {
        $channel = $channel === 'beta' ? 'beta' : ($channel ?? self::channel());
        $latest = self::latest($channel, $force);
        if ($latest === null) {
            return [
                'ok' => false, 'msg' => '连不上版本服务器，请检查 market_url 或网络',
                'current' => SVEL_VERSION, 'latest' => '', 'has_update' => false, 'notes' => '',
                'size' => 0, 'channel' => $channel, 'published_at' => 0,
            ];
        }
        return [
            'ok'           => true,
            'msg'          => '',
            'current'      => SVEL_VERSION,
            'latest'       => $latest['version'],
            'has_update'   => version_compare($latest['version'], SVEL_VERSION, '>'),
            'notes'        => $latest['notes'],
            'size'         => $latest['size'],
            'channel'      => $channel,
            'published_at' => $latest['published_at'],
        ];
    }

    public static function autoEnabled(): bool
    {
        return (string)option('auto_upgrade', '0') === '1';
    }

    /** 后台加载时触发：开启自动升级且距上次检查超过 6 小时才执行 */
    public static function maybeAuto(): ?array
    {
        if (!self::autoEnabled()) {
            return null;
        }
        $last = (int)option('auto_check_at', 0);
        if ($last > 0 && time() - $last < 6 * 3600) {
            return null;
        }
        Option::set('auto_check_at', (string)time());
        try {
            $result = self::upgrade();
            Option::set('auto_upgrade_result', json_encode([
                'time'    => time(),
                'ok'      => $result['ok'],
                'msg'     => $result['msg'],
                'version' => $result['version'],
            ], JSON_UNESCAPED_UNICODE) ?: '{}');
            return $result;
        } catch (\Throwable $e) {
            Log::error('自动升级失败：' . $e->getMessage());
            return ['ok' => false, 'msg' => '自动升级失败：' . $e->getMessage(), 'version' => SVEL_VERSION, 'upgraded' => false];
        }
    }

    /**
     * 执行升级。只覆盖内核 app/ 与入口文件，主题与插件仍由应用市场管理。
     * @param array|null $latest 已在 check 阶段拿到的版本信息
     * @return array{ok:bool,msg:string,version:string,upgraded:bool,backup:string}
     */
    public static function upgrade(?array $latest = null): array
    {
        $from = SVEL_VERSION;
        $result = self::perform($latest);
        if ($result['upgraded'] || !$result['ok']) {
            self::record($from, (string)$result['version'], (bool)$result['ok'], (string)$result['msg'], (bool)$result['upgraded']);
        }
        return $result;
    }

    private static function perform(?array $latest = null): array
    {
        $latest = $latest ?? self::latest();
        if ($latest === null) {
            return ['ok' => false, 'msg' => '连不上版本服务器，无法升级', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
        }
        if (!version_compare($latest['version'], SVEL_VERSION, '>')) {
            return ['ok' => true, 'msg' => '已经是最高版本 ' . SVEL_VERSION, 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
        }

        $base = Market::baseUrl();
        $package = (string)($latest['package'] ?? '');
        if ($base === '' || !preg_match('#^https?://#i', $package) || strpos($package, $base . '/') !== 0) {
            return ['ok' => false, 'msg' => '版本包地址不在市场域名下，已中止（' . $package . '）', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
        }

        $storage = rtrim(str_replace('\\', '/', (string)Config::get('storage.dir', SVEL_ROOT . '/storage')), '/');
        $tmpRoot = $storage . '/tmp';
        $backupDir = $storage . '/backups';
        foreach ([$tmpRoot, $backupDir] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['ok' => false, 'msg' => '目录不可写：' . $dir, 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
            }
        }

        $lock = @fopen($tmpRoot . '/upgrade.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }
            return ['ok' => false, 'msg' => '另一个升级正在进行，请稍后再试', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
        }

        $oldApp = '';
        $newAppDir = '';

        try {
            $zipFile = $tmpRoot . '/cms-' . $latest['version'] . '.zip';
            if (!self::download($latest['package'], $zipFile, $latest['checksum'], $latest['size'], (string)($latest['signature'] ?? ''))) {
                return ['ok' => false, 'msg' => '版本包下载、校验或签名验证失败', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
            }

            $work = $tmpRoot . '/cms-' . $latest['version'] . '-' . bin2hex(random_bytes(3));
            $extracted = self::extract($zipFile, $work);
            if (!$extracted['ok']) {
                @unlink($zipFile);
                return ['ok' => false, 'msg' => $extracted['msg'], 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
            }

            $bootstrap = (string)@file_get_contents($work . '/app/bootstrap.php');
            if (!preg_match("/define\('SVEL_VERSION',\s*'([0-9.]+)'\)/", $bootstrap, $m) || $m[1] !== $latest['version']) {
                Package::removeDir($work);
                @unlink($zipFile);
                return ['ok' => false, 'msg' => '版本包内容与版本号不一致，已中止', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
            }

            $backup = $backupDir . '/cms-' . SVEL_VERSION . '-' . date('YmdHis') . '.zip';
            if (!self::backup($backup)) {
                Package::removeDir($work);
                @unlink($zipFile);
                return ['ok' => false, 'msg' => '备份失败，已中止升级（未改动任何文件）', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
            }

            $oldApp = SVEL_ROOT . '/app.old-' . time();
            if (!@rename(SVEL_ROOT . '/app', $oldApp)) {
                Package::removeDir($work);
                return ['ok' => false, 'msg' => '无法重命名旧 app 目录，请检查站点根目录权限', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => basename($backup)];
            }

            $newAppDir = $work . '/app';
            if (!@rename($newAppDir, SVEL_ROOT . '/app')) {
                @rename($oldApp, SVEL_ROOT . '/app');
                Package::removeDir($work);
                return ['ok' => false, 'msg' => '无法写入新 app 目录，已还原旧版本', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => basename($backup)];
            }

            foreach (['index.php', 'router.php'] as $file) {
                if (is_file($work . '/' . $file)) {
                    @copy($work . '/' . $file, SVEL_ROOT . '/' . $file);
                }
            }

            $sync = Schema::sync();
            if ($sync['errors'] !== []) {
                Log::warn('升级后表结构同步有问题：' . implode('；', $sync['errors']));
                self::restore($oldApp, $backup);
                Package::removeDir($work);
                @unlink($zipFile);
                Cache::flush();
                return ['ok' => false, 'msg' => '表结构同步失败，已还原旧内核：' . implode('；', $sync['errors']), 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => basename($backup)];
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            Cache::flush();

            // 升级后用真实 HTTP 请求自检，失败自动还原旧内核与入口文件
            $health = Installer::httpCode(abs_url(''));
            if ($health !== 200) {
                self::restore($oldApp, $backup);
                Package::removeDir($work);
                @unlink($zipFile);
                Cache::flush();
                return ['ok' => false, 'msg' => '升级后健康检查失败（HTTP ' . $health . '），已自动还原旧内核', 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => basename($backup)];
            }

            Package::removeDir($oldApp);
            Package::removeDir($work);
            @unlink($zipFile);
            self::pruneBackups($backupDir, 5);

            Option::set('last_upgrade_at', (string)time());
            Option::set('last_upgrade_from', SVEL_VERSION);
            Option::set('last_upgrade_to', $latest['version']);
            // 新版本可能新增表或字段，由升级后的新代码在下次请求启动时再同步一次
            Option::set('schema_pending', '1');

            return [
                'ok'       => true,
                'msg'      => '已从 ' . SVEL_VERSION . ' 升级到 ' . $latest['version'] . '，备份：' . basename($backup),
                'version'  => $latest['version'],
                'upgraded' => true,
                'backup'   => basename($backup),
            ];
        } catch (\Throwable $e) {
            if ($newAppDir !== '' && is_dir(SVEL_ROOT . '/app') && $oldApp !== '' && is_dir($oldApp)) {
                Package::removeDir(SVEL_ROOT . '/app');
                @rename($oldApp, SVEL_ROOT . '/app');
            }
            Log::error('升级失败：' . $e->getMessage());
            return ['ok' => false, 'msg' => '升级失败：' . $e->getMessage(), 'version' => SVEL_VERSION, 'upgraded' => false, 'backup' => ''];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** 还原旧内核与入口文件，升级失败时保证站点仍可用 */
    private static function restore(string $oldApp, string $backup): void
    {
        if (!is_dir($oldApp)) {
            return;
        }
        Package::removeDir(SVEL_ROOT . '/app');
        if (!@rename($oldApp, SVEL_ROOT . '/app')) {
            Log::error('升级回滚失败：无法还原 app 目录 ' . $oldApp);
        }
        if (!is_file($backup) || !class_exists('\ZipArchive')) {
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($backup) !== true) {
            return;
        }
        foreach (['index.php', 'router.php'] as $file) {
            $data = $zip->getFromName($file);
            if (is_string($data)) {
                @file_put_contents(SVEL_ROOT . '/' . $file, $data);
            }
        }
        $zip->close();
    }

    /** 备份只保留最近 N 份，避免长期占满磁盘 */
    private static function pruneBackups(string $dir, int $keep): void
    {
        $files = glob($dir . '/cms-*.zip') ?: [];
        usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, max(1, $keep)) as $file) {
            @unlink($file);
        }
    }

    /** 保留最近 10 条升级记录，供后台“升级记录”展示 */
    private static function record(string $from, string $to, bool $ok, string $msg, bool $upgraded): void
    {
        if (self::$history === null) {
            $rows = json_decode((string)option('upgrade_history', '[]'), true);
            self::$history = is_array($rows) ? $rows : [];
        }
        array_unshift(self::$history, [
            'time'     => time(),
            'from'     => $from,
            'to'       => $to,
            'ok'       => $ok,
            'upgraded' => $upgraded,
            'msg'      => $msg,
        ]);
        self::$history = array_slice(self::$history, 0, 10);
        Option::set('upgrade_history', json_encode(self::$history, JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    private static function download(string $url, string $target, string $checksum, int $size, string $signature = ''): bool
    {
        if ($url === '') {
            return false;
        }
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
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_CONNECTTIMEOUT => 8,
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
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            if (!is_string($body) || @file_put_contents($target, $body) === false) {
                return false;
            }
        }

        $actual = (int)@filesize($target);
        if ($actual <= 0 || $actual > 40 * 1024 * 1024) {
            @unlink($target);
            return false;
        }
        if ($checksum !== '' && !hash_equals(strtolower($checksum), hash_file('sha256', $target) ?: '')) {
            @unlink($target);
            return false;
        }
        if (!Signer::verifyFile($target, $signature)) {
            @unlink($target);
            return false;
        }
        return true;
    }

    /** @return array{ok:bool,msg:string} */
    private static function extract(string $zipFile, string $target): array
    {
        if (!class_exists('\ZipArchive')) {
            return ['ok' => false, 'msg' => 'PHP 未启用 zip 扩展，无法解压版本包'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['ok' => false, 'msg' => '版本包无法打开'];
        }
        if ($zip->numFiles > 3000) {
            $zip->close();
            return ['ok' => false, 'msg' => '版本包文件数量异常，已中止'];
        }
        if (!@mkdir($target, 0755, true) && !is_dir($target)) {
            $zip->close();
            return ['ok' => false, 'msg' => '无法创建解压目录'];
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                $zip->close();
                Package::removeDir($target);
                return ['ok' => false, 'msg' => '版本包内含有非法路径'];
            }
            if (str_ends_with($name, '/')) {
                @mkdir($target . '/' . $name, 0755, true);
                continue;
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $content = (string)stream_get_contents($stream);
            fclose($stream);
            $total += strlen($content);
            if ($total > 60 * 1024 * 1024) {
                $zip->close();
                Package::removeDir($target);
                return ['ok' => false, 'msg' => '解压后体积异常，已中止'];
            }
            $path = $target . '/' . $name;
            if (!is_dir(dirname($path))) {
                @mkdir(dirname($path), 0755, true);
            }
            @file_put_contents($path, $content);
        }
        $zip->close();

        if (!is_file($target . '/app/bootstrap.php') || !is_file($target . '/index.php')) {
            Package::removeDir($target);
            return ['ok' => false, 'msg' => '版本包结构不完整（缺少 app/bootstrap.php 或 index.php）'];
        }
        return ['ok' => true, 'msg' => ''];
    }

    private static function backup(string $target): bool
    {
        if (!class_exists('\ZipArchive')) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        foreach (['index.php', 'router.php'] as $file) {
            if (is_file(SVEL_ROOT . '/' . $file)) {
                $zip->addFile(SVEL_ROOT . '/' . $file, $file);
            }
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(SVEL_ROOT . '/app', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'app/' . substr($file->getPathname(), strlen(SVEL_ROOT . '/app') + 1));
            }
        }
        return $zip->close();
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'SvelCMS/' . SVEL_VERSION,
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $code === 200) ? (string)$body : null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }
}