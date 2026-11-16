# SvelCMS（苗条 CMS）

超轻量、SEO 优先的开源博客程序。解压上传、跑一遍 `install.php`，装完就是一个能被搜索引擎正常收录的博客站，默认不依赖任何第三方包、前端框架或外链字体。

- 内核自带 TDK、canonical、JSON-LD、sitemap、robots、RSS、页面缓存
- 数据库：MySQL 5.7+ 默认，SQLite 3 次选，同一份代码双驱动
- 编辑器全部插件化：Markdown 三栏编辑器安装时默认启用，富文本编辑器按需启用；都不启用时就是纯文本框
- slug 可改，旧地址自动 301 到新地址，永不产生多跳
- 主题与插件都放在目录里，`theme.json` / `plugin.json` 即清单，删目录不影响程序启动

## 环境要求

| 项 | 要求 |
| --- | --- |
| PHP | 8.1 以上（开发与验收使用 8.4） |
| 扩展 | `pdo_mysql` 或 `mysqli`（默认库）；`pdo_sqlite` 或 `sqlite3`（次选库）；`mbstring`、`json`、`openssl`、`zip` |
| 数据库 | MySQL 5.7 / 8.0（InnoDB、utf8mb4），或 SQLite 3.8+ |
| 服务器 | Apache / Nginx / LiteSpeed / IIS / Caddy，共享虚拟主机亦可 |

## 安装

1. 把整包上传到站点根目录（或子目录），保持目录结构不变。
2. 浏览器打开 `http://你的域名/install.php`。
3. 依次完成：环境检测 → 数据库 → 站点与管理员 → 安装与自检。
4. 安装完成后**删除或改名 `install.php`**，再用后台账号登录 `/admin/login`。

数据库不存在时安装器会尝试自动建库；账号没有建库权限时，先在主机面板建好库再回来填写即可。SQLite 模式不需要填数据库账号，文件会落在 web 根之外的数据目录。

## 目录

```text
index.php              唯一入口
install.php            安装向导（装完请删除）
router.php             PHP 内置服务器路由（本地开发用）
app/                   内核：Core / Models / Controllers / views
themes/default/        默认主题
plugins/markdown/      Markdown 编辑器插件（默认启用）
plugins/richtext/      富文本编辑器插件（默认停用）
storage/               缓存、上传、日志、规则片段
docs/                  部署与使用说明
```

## 伪静态

安装器会自动探测三档 URL：

| 档位 | 形态 | 说明 |
| --- | --- | --- |
| L1 | `/post/hello.html` | 有重写规则时使用，SEO 最理想 |
| L2 | `/index.php/post/hello.html` | 有 PATH_INFO 但无重写 |
| L3 | `/index.php?p=post/hello.html` | 最后兜底 |

Apache 下 `.htaccess` 已随包提供；Nginx 规则在 `storage/rewrite/nginx.conf`，后台「伪静态」页可一键查看与复制。改完规则后在后台点「重新检测」即可升到 L1。

## 备份

- 需要备份的东西只有三样：数据库、`storage/uploads/`、`config.php`。
- SQLite 模式直接备份数据目录里的 `svel.db` 即可（建议先停写再用 `sqlite3 svel.db ".backup"`）。
- `config.php` 含站点密钥，不要提交到公开仓库。

## 许可

MIT，见 `LICENSE`。