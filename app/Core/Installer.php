<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Installer.php
 * @desc 安装器：环境检测、双数据库安装、数据目录选址、建表播种、URL 档位探测与规则写入
 */

namespace Svel\Core;

use PDO;

final class Installer
{
    /** @return array<int,array{name:string,ok:bool,level:string,note:string}> */
    public static function checks(): array
    {
        $root = SVEL_ROOT;
        $storage = $root . '/storage';
        $mysql = extension_loaded('pdo_mysql') || extension_loaded('mysqli');
        $sqlite = extension_loaded('pdo_sqlite') || extension_loaded('sqlite3');

        return [
            ['name' => 'PHP 版本 ≥ 8.1', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'level' => 'block', 'note' => '当前 ' . PHP_VERSION],
            ['name' => 'MySQL 扩展（pdo_mysql / mysqli）', 'ok' => $mysql, 'level' => 'warn', 'note' => $mysql ? '可用，默认数据库' : '缺失，只能选 SQLite'],
            ['name' => 'SQLite 扩展（pdo_sqlite / sqlite3）', 'ok' => $sqlite, 'level' => 'warn', 'note' => $sqlite ? '可用，次选数据库' : '缺失，只能选 MySQL'],
            ['name' => 'mbstring 扩展', 'ok' => extension_loaded('mbstring'), 'level' => 'block', 'note' => '中文截取与长度校验依赖'],
            ['name' => 'json 扩展', 'ok' => extension_loaded('json'), 'level' => 'block', 'note' => '配置与清单解析依赖'],
            ['name' => 'openssl 扩展', 'ok' => extension_loaded('openssl'), 'level' => 'block', 'note' => '插件包验签依赖'],
            ['name' => 'zip 扩展', 'ok' => extension_loaded('zip'), 'level' => 'warn', 'note' => '缺失时无法在线安装插件包'],
            ['name' => '根目录可写', 'ok' => is_writable($root), 'level' => 'block', 'note' => '需要写入 config.php 与 install.lock'],
            ['name' => 'storage 目录可写', 'ok' => is_dir($storage) ? is_writable($storage) : is_writable($root), 'level' => 'block', 'note' => '缓存、日志、上传依赖'],
            ['name' => '可建立数据目录', 'ok' => is_writable(dirname($root)) || is_writable($root), 'level' => 'warn', 'note' => '优先放在 web 根之外'],
        ];
    }

    public static function mysqlAvailable(): bool
    {
        return extension_loaded('pdo_mysql') || extension_loaded('mysqli');
    }

    public static function sqliteAvailable(): bool
    {
        return extension_loaded('pdo_sqlite') || extension_loaded('sqlite3');
    }

    public static function blocked(array $checks): array
    {
        return array_values(array_filter($checks, static fn(array $c): bool => !$c['ok'] && $c['level'] === 'block'));
    }

    /** 选数据目录：优先 web 根之外，失败回落到 storage/data-随机 */
    public static function chooseDataDir(): string
    {
        $outside = dirname(SVEL_ROOT) . '/svel-data';
        if ((is_dir($outside) || @mkdir($outside, 0755, true)) && is_writable($outside)) {
            return str_replace('\\', '/', $outside);
        }
        $inside = str_replace('\\', '/', SVEL_ROOT) . '/storage/data-' . bin2hex(random_bytes(3));
        if (!is_dir($inside)) {
            @mkdir($inside, 0755, true);
        }
        return $inside;
    }

    /**
     * 测试数据库连接，不修改任何数据。
     * @return array{ok:bool,msg:string,version:string}
     */
    public static function testDb(array $cfg): array
    {
        try {
            if (($cfg['driver'] ?? '') === 'sqlite') {
                if (!self::sqliteAvailable()) {
                    return ['ok' => false, 'msg' => 'PHP 未安装 SQLite 扩展', 'version' => ''];
                }
                $file = (string)($cfg['file'] ?? '');
                $dir = dirname($file);
                if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                    return ['ok' => false, 'msg' => '数据目录无法创建：' . $dir, 'version' => ''];
                }
                $pdo = new PDO('sqlite:' . $file);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $v = (string)$pdo->query('SELECT sqlite_version()')->fetchColumn();
                return ['ok' => true, 'msg' => 'SQLite 连接成功，数据库文件：' . $file, 'version' => $v];
            }

            if (!self::mysqlAvailable()) {
                return ['ok' => false, 'msg' => 'PHP 未安装 pdo_mysql / mysqli 扩展', 'version' => ''];
            }
            if (($cfg['database'] ?? '') === '') {
                return ['ok' => false, 'msg' => '数据库名不能为空', 'version' => ''];
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string)$cfg['host'],
                (int)$cfg['port'],
                (string)$cfg['database']
            );
            $pdo = new PDO($dsn, (string)$cfg['username'], (string)$cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $v = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare(preg_replace('/[^0-9.].*$/', '', $v) ?: '0', '5.7.0', '<')) {
                return ['ok' => false, 'msg' => 'MySQL 版本过低（' . $v . '），需要 5.7 以上', 'version' => $v];
            }
            return ['ok' => true, 'msg' => 'MySQL 连接成功，版本 ' . $v, 'version' => $v];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => self::friendlyDbError($e->getMessage()), 'version' => ''];
        }
    }

    private static function friendlyDbError(string $message): string
    {
        if (str_contains($message, '1049')) {
            return '数据库不存在。请先在面板创建数据库，或在下方勾选「自动建库」（账号需有 CREATE 权限）。';
        }
        if (str_contains($message, '1045')) {
            return '账号或密码不正确，请核对数据库用户名与密码。';
        }
        if (str_contains($message, '2002') || str_contains($message, '2003')) {
            return '连不上数据库服务器，请检查地址与端口，或确认 MySQL 服务已启动。';
        }
        if (stripos($message, 'could not find driver') !== false) {
            return 'PHP 缺少对应数据库驱动，请在面板开启扩展后重启 PHP。';
        }
        return $message;
    }

    /** MySQL 目标库不存在时尝试创建，失败不阻断，交给后续步骤报错 */
    public static function ensureDatabase(array $cfg): void
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', (string)$cfg['host'], (int)$cfg['port']);
        $pdo = new PDO($dsn, (string)$cfg['username'], (string)$cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $name = str_replace('`', '', (string)$cfg['database']);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * 执行建表。MySQL 的 DDL 会隐式提交，失败时按已建表逆序清理。
     * @return array{ok:bool,msg:string,tables:array<int,string>}
     */
    public static function runSchema(): array
    {
        $db = Db::inst();
        $ddl = Schema::ddl($db->driver(), $db->prefix());
        $created = [];
        $tableNames = Schema::tableNames();

        try {
            foreach ($ddl as $i => $sql) {
                $db->pdo()->exec($sql);
                if (str_starts_with($sql, 'CREATE TABLE')) {
                    $created[] = $tableNames[$i] ?? ('#' . $i);
                }
            }
            return ['ok' => true, 'msg' => '建表完成，共 ' . count($tableNames) . ' 张表', 'tables' => $tableNames];
        } catch (\Throwable $e) {
            if ($db->driver() === 'mysql') {
                foreach (array_reverse($created) as $t) {
                    try {
                        $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->qi($db->table($t)));
                    } catch (\Throwable) {
                        // 清理失败也要继续把原始错误抛给用户
                    }
                }
            }
            return ['ok' => false, 'msg' => '建表失败：' . self::friendlyDbError($e->getMessage()), 'tables' => []];
        }
    }

    /** 写入站点初始数据 */
    public static function seed(array $data): void
    {
        $db = Db::inst();
        $now = time();

        $options = [
            'site.title'       => (string)$data['title'],
            'site.tagline'     => (string)$data['tagline'],
            'site.description' => (string)($data['description'] ?? ''),
            'site.keywords'    => (string)($data['keywords'] ?? ''),
            'site.icp'         => '',
            'site.theme'       => 'default',
            'site.posts_per_page' => '10',
            'site.footer_text' => 'Powered by SvelCMS',
            'cache.ttl'        => '3600',
            'seo.default_image' => '',
        ];
        foreach ($options as $name => $value) {
            $db->insert('option', ['name' => $name, 'value' => $value]);
        }

        $adminId = $db->insert('user', [
            'username'      => (string)$data['username'],
            'password_hash' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
            'nickname'      => (string)$data['username'],
            'email'         => (string)($data['email'] ?? ''),
            'role'          => 'admin',
            'created_at'    => $now,
        ]);

        $catId = $db->insert('category', [
            'name'        => '默认分类',
            'slug'        => 'default',
            'description' => '没有归类的文章都放在这里',
            'parent_id'   => 0,
            'sort'        => 0,
        ]);

        $db->insert('post', [
            'title'          => '欢迎使用 SvelCMS',
            'slug'           => 'hello-svelcms',
            'content'        => "## 这是你的第一篇文章\n\nSvelCMS 是一个超轻量、SEO 优先的博客程序。你现在看到的这段文字用的是 **Markdown**。\n\n- 后台可以随时修改这篇文章\n- 换主题、装插件都在后台点选完成\n- 站点地图、RSS、结构化数据默认就有\n\n写点属于你自己的东西吧。",
            'content_format' => 'markdown',
            'excerpt'        => '',
            'cover'          => '',
            'status'         => 'publish',
            'type'           => 'post',
            'category_id'    => $catId,
            'author_id'      => $adminId,
            'views'          => 0,
            'is_top'         => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
            'published_at'   => $now,
        ]);

        self::registerThemes();
        self::registerPlugins();
    }

    public static function registerThemes(): void
    {
        $db = Db::inst();
        $dirs = glob(SVEL_ROOT . '/themes/*', GLOB_ONLYDIR) ?: [];
        $first = true;
        foreach ($dirs as $dir) {
            $json = @file_get_contents($dir . '/theme.json');
            $meta = is_string($json) ? json_decode($json, true) : null;
            $slug = basename($dir);
            if (!is_array($meta)) {
                continue;
            }
            $exists = $db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('theme')) . ' WHERE ' . $db->qi('slug') . ' = ?', [$slug], 0);
            if ((int)$exists > 0) {
                continue;
            }
            $db->insert('theme', [
                'slug'         => $slug,
                'name'         => (string)($meta['name'] ?? $slug),
                'version'      => (string)($meta['version'] ?? '1.0.0'),
                'status'       => $slug === 'default' ? 1 : 0,
                'installed_at' => time(),
            ]);
            if ($slug === 'default') {
                $first = false;
            }
        }
        unset($first);
    }

    /** 扫描 plugins/ 注册已有插件；Markdown 编辑器默认启用，富文本保持停用 */
    public static function registerPlugins(): void
    {
        $db = Db::inst();
        $defaultOn = ['markdown'];
        foreach (glob(SVEL_ROOT . '/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $json = @file_get_contents($dir . '/plugin.json');
            $meta = is_string($json) ? json_decode($json, true) : null;
            $slug = basename($dir);
            if (!is_array($meta)) {
                continue;
            }
            $exists = $db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('plugin')) . ' WHERE ' . $db->qi('slug') . ' = ?', [$slug], 0);
            if ((int)$exists > 0) {
                continue;
            }
            $db->insert('plugin', [
                'slug'         => $slug,
                'name'         => (string)($meta['name'] ?? $slug),
                'version'      => (string)($meta['version'] ?? '1.0.0'),
                'status'       => in_array($slug, $defaultOn, true) ? 1 : 0,
                'hooks'        => json_encode($meta['hooks'] ?? [], JSON_UNESCAPED_UNICODE),
                'source'       => 'builtin',
                'priority'     => (int)($meta['priority'] ?? 10),
                'checksum'     => '',
                'installed_at' => time(),
            ]);
        }
    }

public static function writeConfig(array $site, array $db, string $dataDir, int $urlMode, string $marketUrl = ''): void
    {
        $config = [
            'db'         => $db,
            'site'       => [
                'url'      => rtrim((string)$site['url'], '/'),
                'base'     => (string)$site['base'],
                'timezone' => 'Asia/Shanghai',
            ],
            'storage'    => ['dir' => $dataDir],
            'url_mode'   => $urlMode,
            'theme'      => 'default',
            'debug'      => false,
'secret'     => bin2hex(random_bytes(32)),
            'market_url' => rtrim($marketUrl, '/'),
        ];

        self::saveConfig($config);
    }

    /** 把完整配置数组写回 config.php，后台切换 URL 档位时复用 */
    public static function saveConfig(array $config): void
    {
        $php = "<?php

declare(strict_types=1);

// 由安装器生成，含密钥，请勿提交到版本库或公开
return "
            . self::exportArray($config, 0) . ";
";
        $tmp = SVEL_ROOT . '/config.php.' . getmypid() . '.tmp';
        file_put_contents($tmp, $php);
if (!@rename($tmp, SVEL_ROOT . '/config.php')) {
            @copy($tmp, SVEL_ROOT . '/config.php');
            @unlink($tmp);
        }
        @chmod(SVEL_ROOT . '/config.php', 0640);
    }

    /** 把配置数组导出成 PHP 字面量，递归不依赖闭包自引用 */
    private static function exportArray(array $arr, int $level): string
    {
        $pad = str_repeat('    ', $level);
        $out = "[\n";
        foreach ($arr as $k => $v) {
            $out .= $pad . '    ' . var_export((string)$k, true) . ' => ';
            if (is_array($v)) {
                $out .= self::exportArray($v, $level + 1) . ",\n";
            } elseif (is_bool($v)) {
                $out .= ($v ? 'true' : 'false') . ",\n";
            } elseif (is_int($v)) {
                $out .= $v . ",\n";
            } else {
                $out .= var_export((string)$v, true) . ",\n";
            }
        }
        return $out . $pad . ']';
    }

    /** 安装失败重来时清掉残留表，避免半套结构与重复主键 */
    public static function reset(): void
    {
        $db = Db::inst();
        if ($db->driver() === 'sqlite') {
            $db->pdo()->exec('PRAGMA foreign_keys = OFF');
        }
        foreach (array_reverse(Schema::tableNames()) as $table) {
            try {
                $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->qi($db->table($table)));
            } catch (\Throwable) {
            }
        }
        if ($db->driver() === 'sqlite') {
            $db->pdo()->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** @return array{mode:int,note:string} */
    public static function detectUrlMode(string $siteUrl): array
    {
        // PHP 内置服务器是单进程，安装期间自请求会死锁；router.php 接管全部路径，必然是 L1
        if (PHP_SAPI === 'cli-server') {
            return ['mode' => 1, 'note' => '内置服务器（开发模式），全部请求由 router.php 接管'];
        }

        $token = 'p' . bin2hex(random_bytes(4));
        $base = '/' . ltrim($token, '/');

        if (self::probeOk($siteUrl . $base . '/__svel_probe_' . $token)) {
            return ['mode' => 1, 'note' => '伪静态生效，使用干净 URL'];
        }
        if (self::probeOk($siteUrl . '/index.php' . $base . '/__svel_probe_' . $token)) {
            return ['mode' => 2, 'note' => '伪静态未生效，降级为 PATH_INFO'];
        }
        return ['mode' => 3, 'note' => '伪静态与 PATH_INFO 均不可用，降级为查询串'];
    }

    private static function probeOk(string $url): bool
    {
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) && str_contains($body, 'SVEL_PROBE_OK');
    }

    /** Apache 规则按 base 改写；Nginx/Caddy 规则写到 storage/rewrite 供复制 */
    public static function writeRewrite(int $mode, string $base): void
    {
        $dir = SVEL_ROOT . '/storage/rewrite';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if ($mode === 1) {
            $ht = "# BEGIN SvelCMS\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteBase " . ($base === '' ? '/' : $base . '/') . "\n\n"
                . "    RewriteCond %{REQUEST_FILENAME} -f [OR]\n    RewriteCond %{REQUEST_FILENAME} -d\n    RewriteRule ^ - [L]\n\n"
. "    RewriteRule ^storage/(?!uploads/).* - [F,L]\n    RewriteRule ^(dist|shichang|docs)/ - [F,L]\n    RewriteRule ^config/ - [F,L]\n\n"
                . "    RewriteRule ^ index.php [L]\n</IfModule>\n"
                . "<FilesMatch \"^(config\\.php|install\\.php|install\\.lock|\\.user\\.ini|\\.htaccess|.*\\.db|.*\\.sqlite|.*\\.md)$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n</FilesMatch>\nOptions -Indexes\n# END SvelCMS\n";
            $root = SVEL_ROOT . '/.htaccess';
            $old = is_file($root) ? (string)file_get_contents($root) : '';
            $new = preg_match('/# BEGIN SvelCMS.*?# END SvelCMS/s', $old)
                ? (string)preg_replace('/# BEGIN SvelCMS.*?# END SvelCMS/s', $ht, $old)
                : ($old === '' ? $ht : rtrim($old) . "\n\n" . $ht);
            file_put_contents($root, $new);
            file_put_contents($dir . '/apache.txt', $ht);
        }

        $location = $base === '' ? '' : $base;
        $nginx = "# 放到 server { } 内，且位于 location ~ \\.php\$ 之前\n"
            . "location " . ($base === '' ? '/' : $base . '/') . " {\n"
            . "    try_files \$uri \$uri/ " . $location . "/index.php?\$args;\n}\n";
        file_put_contents($dir . '/nginx.conf', $nginx);

        $caddy = ":80 {\n    try_files {path} " . ($base === '' ? '' : $base) . "/index.php?{query}\n}\n";
        file_put_contents($dir . '/caddy.txt', $caddy);

        file_put_contents($dir . '/iis.txt', "<!-- web.config 内 system.webServer 节 -->\n"
            . "<rewrite><rules><rule name=\"SvelCMS\" stopProcessing=\"true\">\n"
            . "<match url=\"^(.*)\$\" /><conditions><add input=\"{REQUEST_FILENAME}\" matchType=\"IsFile\" negate=\"true\" />"
            . "<add input=\"{REQUEST_FILENAME}\" matchType=\"IsDirectory\" negate=\"true\" /></conditions>\n"
            . "<action type=\"Rewrite\" url=\"index.php\" /></rule></rules></rewrite>\n");
    }

    /**
     * 安装后自检：首页、示例文章、sitemap、404
     * @return array<int,array{name:string,ok:?bool,note:string}>
     */
    public static function selfCheck(string $siteUrl, string $postSlug): array
    {
        if (PHP_SAPI === 'cli-server') {
            return [[
                'name' => '自检（内置服务器）',
                'ok'   => null,
                'note' => '内置服务器为单进程，安装期间无法自请求；请安装后用浏览器访问首页、示例文章、sitemap 与一个不存在的地址确认',
            ]];
        }

        $cases = [
            ['首页可访问', $siteUrl . '/', 200],
            ['示例文章可访问', $siteUrl . '/post/' . $postSlug . '.html', 200],
            ['站点地图可访问', $siteUrl . '/sitemap.xml', 200],
            ['不存在的地址返回 404', $siteUrl . '/this-page-should-not-exist-' . bin2hex(random_bytes(3)), 404],
        ];
        $out = [];
        foreach ($cases as [$name, $url, $expect]) {
            $code = self::httpCode($url);
            $out[] = [
                'name' => $name,
                'ok'   => $code === $expect,
                'note' => '期望 ' . $expect . '，实际 ' . ($code === 0 ? '请求失败' : (string)$code),
            ];
        }
        return $out;
    }

    public static function httpCode(string $url): int
    {
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0],
        ]);
        @file_get_contents($url, false, $ctx);
        $headers = $http_response_header ?? [];
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$h, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    public static function writeLock(): void
    {
        file_put_contents(SVEL_ROOT . '/install.lock', 'installed at ' . date('c') . "\n");
    }

    public static function markInstalled(): void
    {
        self::writeLock();
    }
}