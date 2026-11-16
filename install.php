<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file install.php
 * @desc 安装向导：环境检测 → 数据库 → 站点与管理员 → 执行安装与自检
 */

require __DIR__ . '/app/bootstrap.php';

use Svel\Core\Db;
use Svel\Core\Installer;

session_name('svel_install');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function inst_base(): string
{
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/install.php');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' ? '' : $dir;
}

function inst_guess_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . inst_base();
}

function inst_redirect(int $step): never
{
    header('Location: install.php?step=' . $step);
    exit;
}

function inst_token(): string
{
    if (empty($_SESSION['inst_token'])) {
        $_SESSION['inst_token'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['inst_token'];
}

function inst_csrf_ok(): bool
{
    $sent = (string)($_POST['_token'] ?? '');
    return $sent !== '' && hash_equals(inst_token(), $sent);
}

function inst_state(string $key, mixed $default = null): mixed
{
    return $_SESSION['inst'][$key] ?? $default;
}

function inst_set(string $key, mixed $value): void
{
    $_SESSION['inst'][$key] = $value;
}

function inst_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$installed = is_file(__DIR__ . '/config.php') && is_file(__DIR__ . '/install.lock');
$step = (int)($_GET['step'] ?? 1);
$step = max(1, min(4, $step));
$errors = [];
$notices = [];
$result = null;

// —— 环境检测 ——
$checks = Installer::checks();
$blocked = Installer::blocked($checks);

// —— 步骤 2：数据库表单提交 ——
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    if (!inst_csrf_ok()) {
        $errors[] = '页面已过期，请重新提交。';
    } else {
        $driver = (string)($_POST['driver'] ?? 'mysql');
        $dataDir = (string)inst_state('data_dir', '');
        if ($dataDir === '') {
            $dataDir = Installer::chooseDataDir();
            inst_set('data_dir', $dataDir);
        }

        $cfg = ['driver' => $driver, 'prefix' => trim((string)($_POST['prefix'] ?? 'svel_')) ?: 'svel_'];
        if ($cfg['prefix'] !== '' && !preg_match('/^[A-Za-z0-9_]+$/', (string)$cfg['prefix'])) {
            $errors[] = '表前缀只允许字母、数字和下划线。';
        }

        if ($driver === 'sqlite') {
            $cfg['file'] = rtrim($dataDir, '/') . '/svel.db';
        } else {
            $cfg['host'] = trim((string)($_POST['host'] ?? '127.0.0.1')) ?: '127.0.0.1';
            $cfg['port'] = (int)($_POST['port'] ?? 3306) ?: 3306;
            $cfg['database'] = trim((string)($_POST['database'] ?? ''));
            $cfg['username'] = trim((string)($_POST['username'] ?? ''));
            $cfg['password'] = (string)($_POST['password'] ?? '');
            $cfg['charset'] = 'utf8mb4';
            $cfg['collation'] = 'utf8mb4_unicode_ci';
        }

        if ($errors === []) {
            $test = Installer::testDb($cfg);
            if (!$test['ok']) {
                $errors[] = $test['msg'];
            } else {
                inst_set('db', $cfg);
                inst_set('db_version', $test['version']);
                inst_redirect(3);
            }
        }
    }
}

// —— 步骤 3：站点信息提交并开始安装 ——
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    if (!inst_csrf_ok()) {
        $errors[] = '页面已过期，请重新提交。';
    } else {
        $site = [
            'url'         => rtrim(trim((string)($_POST['site_url'] ?? '')), '/'),
            'base'        => (string)inst_base(),
            'title'       => trim((string)($_POST['title'] ?? '')),
            'tagline'     => trim((string)($_POST['tagline'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'keywords'    => trim((string)($_POST['keywords'] ?? '')),
            'username'    => trim((string)($_POST['username'] ?? '')),
            'password'    => (string)($_POST['password'] ?? ''),
            'password2'   => (string)($_POST['password2'] ?? ''),
            'email'       => trim((string)($_POST['email'] ?? '')),
            'market_url'  => rtrim(trim((string)($_POST['market_url'] ?? '')), '/'),
        ];

        if ($site['url'] === '' || !preg_match('#^https?://#i', $site['url'])) {
            $errors[] = '站点地址要以 http:// 或 https:// 开头。';
        }
        if ($site['title'] === '') {
            $errors[] = '站点名称不能为空。';
        }
        if ($site['market_url'] !== '' && !preg_match('#^https?://#i', $site['market_url'])) {
            $errors[] = '应用市场地址要以 http:// 或 https:// 开头，或留空。';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $site['username'])) {
            $errors[] = '管理员账号只能是 3–20 位字母、数字或下划线。';
        }
        if (mb_strlen($site['password']) < 10) {
            $errors[] = '管理员密码至少 10 位。';
        }
        if (!preg_match('/[A-Za-z]/', $site['password']) || !preg_match('/[0-9]/', $site['password'])) {
            $errors[] = '管理员密码必须同时包含字母和数字。';
        }
        if ($site['password'] !== $site['password2']) {
            $errors[] = '两次输入的密码不一致。';
        }
        if ($site['email'] !== '' && !filter_var($site['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式不正确。';
        }

        $dbCfg = (array)inst_state('db', []);
        if ($dbCfg === []) {
            $errors[] = '数据库配置丢失，请回到上一步重新填写。';
        }

        if ($errors === []) {
            $dataDir = (string)inst_state('data_dir', Installer::chooseDataDir());
            try {
                if (($dbCfg['driver'] ?? '') === 'mysql') {
                    try {
                        Installer::ensureDatabase($dbCfg);
                    } catch (Throwable $e) {
                        $notices[] = '自动建库未成功（多半是账号没有 CREATE 权限），继续尝试连接已有数据库。';
                    }
                }

                Db::boot($dbCfg);
                if (!is_file(__DIR__ . '/config.php')) {
                    Installer::reset();
                }
                $schema = Installer::runSchema();
                if (!$schema['ok']) {
                    throw new RuntimeException($schema['msg']);
                }

                Installer::seed($site);
                Installer::writeConfig($site, $dbCfg, $dataDir, 1, $site['market_url']);

                $detect = Installer::detectUrlMode($site['url']);
                Installer::writeRewrite($detect['mode'], $site['base']);
                Installer::writeConfig($site, $dbCfg, $dataDir, $detect['mode'], $site['market_url']);
                Installer::writeLock();

                $selfCheck = Installer::selfCheck($site['url'], 'hello-svelcms');
                $result = [
                    'schema'  => $schema,
                    'mode'    => $detect,
                    'checks'  => $selfCheck,
                    'url'     => $site['url'],
                    'admin'   => $site['username'],
                    'db'      => $dbCfg['driver'] === 'sqlite' ? 'SQLite ' . (string)inst_state('db_version', '') : 'MySQL ' . (string)inst_state('db_version', ''),
                    'dataDir' => $dataDir,
                ];
                $_SESSION['inst'] = [];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

function inst_ui_head(string $title): void
{
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . inst_h($title) . ' - SvelCMS 安装</title><style>'
        . '*{box-sizing:border-box}body{margin:0;background:#f5f6f7;color:#222;font:15px/1.7 system-ui,-apple-system,"Microsoft YaHei",sans-serif}'
        . '.wrap{max-width:760px;margin:48px auto 64px;padding:0 20px}'
        . '.card{background:#fff;border:1px solid #e3e5e8;border-radius:10px;padding:28px 32px;box-shadow:0 2px 10px rgba(0,0,0,.04)}'
        . 'h1{font-size:22px;margin:0 0 6px}h2{font-size:17px;margin:28px 0 10px}'
        . '.sub{color:#777;margin:0 0 22px;font-size:13px}'
        . 'table{width:100%;border-collapse:collapse;font-size:14px}td,th{padding:8px 6px;border-bottom:1px solid #eee;text-align:left}'
        . '.ok{color:#137333}.bad{color:#c5221f}.warn{color:#b06000}'
        . 'label{display:block;margin:14px 0 6px;font-size:13px;color:#444}'
        . 'input[type=text],input[type=password],input[type=url],input[type=email],input[type=number],select{width:100%;padding:9px 11px;border:1px solid #d5d8dc;border-radius:6px;font-size:14px}'
        . '.row{display:flex;gap:14px}.row>div{flex:1}'
        . '.btn{display:inline-block;margin-top:22px;background:#1a73e8;color:#fff;border:0;border-radius:6px;padding:11px 22px;font-size:14px;cursor:pointer}'
        . '.btn:disabled{background:#b9c6d8;cursor:not-allowed}'
        . '.err{background:#fdecea;border:1px solid #f6c7c3;color:#a61b16;padding:10px 14px;border-radius:6px;margin:0 0 14px;font-size:13px}'
        . '.note{background:#fff8e5;border:1px solid #f3dfae;color:#7a5300;padding:10px 14px;border-radius:6px;margin:0 0 14px;font-size:13px}'
        . '.pass{background:#e8f5e9;border:1px solid #bfe0c2;padding:12px 14px;border-radius:6px;font-size:13px}'
        . 'code{background:#f1f3f5;padding:1px 5px;border-radius:4px;font-size:12px}'
        . '.foot{margin-top:22px;color:#999;font-size:12px;text-align:center}'
        . '</style></head><body><div class="wrap"><div class="card">';
}

function inst_ui_foot(): void
{
    echo '</div><p class="foot">SvelCMS ' . inst_h(SVEL_VERSION) . ' · 超轻量 SEO 博客程序</p></div></body></html>';
}

inst_ui_head('安装向导');

if ($installed && $result === null) {
    echo '<h1>已经安装过了</h1><p class="sub">检测到 <code>config.php</code> 与 <code>install.lock</code>。</p>'
        . '<p>如需重新安装，请先备份并删除根目录下的 <code>install.lock</code>（以及你想重建的 <code>config.php</code>），再刷新本页。</p>'
        . '<p>出于安全考虑，安装完成后请删除或改名 <code>install.php</code>。</p>'
        . '<p><a class="btn" href="' . inst_h(inst_base() . '/admin/login') . '">进入后台</a></p>';
    inst_ui_foot();
    exit;
}

echo '<h1>SvelCMS 安装向导</h1><p class="sub">共四步：环境检测 → 数据库 → 站点与管理员 → 完成</p>';

foreach ($errors as $err) {
    echo '<div class="err">' . inst_h($err) . '</div>';
}
foreach ($notices as $n) {
    echo '<div class="note">' . inst_h($n) . '</div>';
}

// ————— 结果页 —————
if ($result !== null) {
    echo '<h2>安装完成</h2><div class="pass">数据库：' . inst_h((string)$result['db']) . '；URL 档位：L' . (int)$result['mode']['mode'] . '（' . inst_h($result['mode']['note']) . '）</div>';
    echo '<h2>自检</h2><table><tr><th>检查项</th><th>结果</th><th>说明</th></tr>';
    foreach ($result['checks'] as $c) {
        $state = $c['ok'] === null ? ['warn', '跳过'] : ($c['ok'] ? ['ok', '通过'] : ['bad', '未通过']);
        echo '<tr><td>' . inst_h($c['name']) . '</td><td class="' . $state[0] . '">' . $state[1] . '</td><td>' . inst_h($c['note']) . '</td></tr>';
    }
    echo '</table>';
    echo '<h2>接下来</h2><p>后台地址：<code>' . inst_h($result['url'] . '/admin/login') . '</code><br>管理员账号：<code>' . inst_h((string)$result['admin']) . '</code></p>'
        . '<p>数据目录：<code>' . inst_h((string)$result['dataDir']) . '</code></p>'
        . '<div class="note">安全提示：请删除或改名 <code>install.php</code>；如果数据目录落在站点目录内，确认已写入封锁规则。</div>'
        . '<p><a class="btn" href="' . inst_h($result['url'] . '/') . '">查看站点</a> '
        . '<a class="btn" style="background:#444" href="' . inst_h($result['url'] . '/admin/login') . '">进入后台</a></p>';
    inst_ui_foot();
    exit;
}

// ————— 步骤 1：环境 —————
if ($step === 1) {
    echo '<h2>第一步：环境检测</h2><table><tr><th>项目</th><th>状态</th><th>说明</th></tr>';
    foreach ($checks as $c) {
        $cls = $c['ok'] ? 'ok' : ($c['level'] === 'block' ? 'bad' : 'warn');
        $state = $c['ok'] ? '通过' : ($c['level'] === 'block' ? '不通过（阻断）' : '警告');
        echo '<tr><td>' . inst_h($c['name']) . '</td><td class="' . $cls . '">' . $state . '</td><td>' . inst_h($c['note']) . '</td></tr>';
    }
    echo '</table>';

    if ($blocked !== []) {
        echo '<div class="err">存在阻断项，请先解决后再继续。修改 PHP 设置后需要重启 PHP 或 Web 服务器。</div>';
    } elseif (!Installer::mysqlAvailable() && !Installer::sqliteAvailable()) {
        echo '<div class="err">MySQL 与 SQLite 扩展都不可用，无法继续。</div>';
    } else {
        echo '<form method="get"><input type="hidden" name="step" value="2"><button class="btn" type="submit">下一步</button></form>';
    }
    inst_ui_foot();
    exit;
}

// ————— 步骤 2：数据库 —————
if ($step === 2) {
    $saved = (array)inst_state('db', []);
    $driver = (string)($saved['driver'] ?? (Installer::mysqlAvailable() ? 'mysql' : 'sqlite'));
    $dataDir = (string)inst_state('data_dir', '');
    if ($dataDir === '') {
        $dataDir = Installer::chooseDataDir();
        inst_set('data_dir', $dataDir);
    }
    echo '<h2>第二步：数据库</h2>';
    echo '<p class="sub">默认 MySQL；没有 MySQL 或不想用，就选 SQLite，数据库文件会放在数据目录里。</p>';
    echo '<form method="post" action="install.php?step=2"><input type="hidden" name="_token" value="' . inst_h(inst_token()) . '">';
    echo '<label>数据库类型</label><select name="driver" id="driver">'
        . '<option value="mysql"' . ($driver === 'mysql' ? ' selected' : '') . (Installer::mysqlAvailable() ? '' : ' disabled') . '>MySQL（默认）</option>'
        . '<option value="sqlite"' . ($driver === 'sqlite' ? ' selected' : '') . (Installer::sqliteAvailable() ? '' : ' disabled') . '>SQLite（次选）</option>'
        . '</select>';

    echo '<div id="mysql-box"><div class="row"><div><label>主机</label><input type="text" name="host" value="' . inst_h((string)($saved['host'] ?? '127.0.0.1')) . '"></div>'
        . '<div><label>端口</label><input type="number" name="port" value="' . inst_h((string)($saved['port'] ?? 3306)) . '"></div></div>'
        . '<label>数据库名</label><input type="text" name="database" value="' . inst_h((string)($saved['database'] ?? '')) . '">'
        . '<label>数据库用户名</label><input type="text" name="username" value="' . inst_h((string)($saved['username'] ?? '')) . '">'
        . '<label>数据库密码</label><input type="password" name="password" value="">'
        . '<p class="sub">数据库不存在时会尝试自动创建；账号没有建库权限也没关系，先在面板建好库再回来填即可。</p></div>';

    echo '<div id="sqlite-box"><p class="sub">数据库文件：<code>' . inst_h(rtrim($dataDir, '/') . '/svel.db') . '</code></p></div>';

    echo '<label>表前缀</label><input type="text" name="prefix" value="' . inst_h((string)($saved['prefix'] ?? 'svel_')) . '">'
        . '<button class="btn" type="submit">测试连接并继续</button></form>';

    echo '<script>(function(){var d=document.getElementById("driver"),m=document.getElementById("mysql-box"),s=document.getElementById("sqlite-box");'
        . 'function t(){var v=d.value==="sqlite";m.style.display=v?"none":"";s.style.display=v?"":"none";}d.addEventListener("change",t);t();})();</script>';
    inst_ui_foot();
    exit;
}

// ————— 步骤 3：站点与管理员 —————
if ($step === 3) {
    $guess = inst_guess_url();
    echo '<h2>第三步：站点与管理员</h2>';
    echo '<form method="post" action="install.php?step=3"><input type="hidden" name="_token" value="' . inst_h(inst_token()) . '">';
    echo '<label>站点地址（含子目录，末尾不要斜杠）</label><input type="url" name="site_url" value="' . inst_h($guess) . '">'
        . '<label>站点名称</label><input type="text" name="title" value="我的 SvelCMS 站点">'
        . '<label>副标题（可空，用于首页 title 与站点描述）</label><input type="text" name="tagline" value="">'
        . '<label>站点描述（可空，SEO description 与 RSS 用）</label><input type="text" name="description" value="">'
        . '<label>关键词（可空，逗号分隔）</label><input type="text" name="keywords" value="">'
        . '<label>应用市场地址（可空，用于插件与系统升级）</label><input type="url" name="market_url" value="" placeholder="http://app.svelcms.com">'
        . '<div class="row"><div><label>管理员账号</label><input type="text" name="username" value="admin"></div>'
        . '<div><label>邮箱（可空）</label><input type="email" name="email" value=""></div></div>'
        . '<div class="row"><div><label>管理员密码（至少 10 位，含字母和数字）</label><input type="password" name="password" value=""></div>'
        . '<div><label>再输一次</label><input type="password" name="password2" value=""></div></div>';
    echo '<button class="btn" type="submit">开始安装</button></form>';
    echo '<p class="sub" style="margin-top:16px">数据库：<code>' . inst_h(($saved = (array)inst_state('db', [])) ? (string)($saved['driver'] ?? '') : '') . '</code></p>';
    inst_ui_foot();
    exit;
}

inst_redirect(1);