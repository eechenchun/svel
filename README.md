# SvelCMS

**超轻量、SEO 优先的开源 PHP 博客 CMS。**  
零 Composer、零 npm、零前端框架，上传即用；把「轻」和「能被搜到」同时做到位。

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-2ea44f)](LICENSE)
[![Dependencies](https://img.shields.io/badge/dependencies-zero-6f42c1)](#)
[![SEO](https://img.shields.io/badge/SEO-first-0a66c2)](#)

[在线演示](https://svelcms.com) · [应用市场](https://app.svelcms.com) · [部署文档](docs/deploy.md)

---

## 为什么是 SvelCMS

很多 CMS 的功能越加越多，真正影响收录与访问速度的部分却被层层框架拖慢。SvelCMS 走另一条路：

- **足够轻**：没有 Composer 依赖，没有 Node 构建链，没有外链字体和前端框架；解压上传即可运行。
- **足够快**：页面缓存、按需加载、精简 SQL，默认配置就能在普通虚拟主机上跑出稳定响应。
- **足够 SEO**：TDK、canonical、JSON-LD、sitemap、robots、RSS 和分页规范由内核直接生成，不依赖插件拼装。
- **足够稳**：CSRF、登录限速、两步验证、邮箱验证、审计日志、上传白名单与发布包签名校验全部内置。
- **足够开放**：主题与插件都是普通目录 + JSON 清单，删掉目录不影响程序启动；应用市场可独立部署。

## 核心能力

| 能力 | 说明 |
| --- | --- |
| 双数据库驱动 | MySQL 5.7+ 默认，SQLite 3.8+ 次选；同一份代码，安装时选择 |
| 双编辑器模式 | 纯 Markdown、富文本或纯文本框；插件化启用，互不绑架 |
| SEO 内核 | 标题/描述/关键词、canonical、JSON-LD、sitemap.xml、robots.txt、RSS、分页 rel 链接 |
| URL 治理 | slug 可修改，旧地址自动 301 到新地址；支持三档伪静态形态 |
| 主题系统 | `theme.json` 即清单，模板可覆盖，默认主题开箱可用 |
| 插件系统 | `plugin.json` 即清单，支持设置项、启用/停用与卸载清理 |
| 应用市场 | 独立市场站点，负责插件/主题分发、开发者入驻与 CMS 版本升级 |
| 安全基线 | 2FA、邮箱验证、审计日志、登录限速、上传封禁、缓存白名单、签名升级包 |
| 轻量部署 | Apache / Nginx / LiteSpeed / IIS / Caddy，共享虚拟主机亦可 |

## 环境要求

| 项 | 要求 |
| --- | --- |
| PHP | 8.1 以上（开发与验收使用 8.4） |
| 扩展 | `pdo_mysql` 或 `mysqli`；`pdo_sqlite` 或 `sqlite3`；`mbstring`、`json`、`openssl`、`zip` |
| 数据库 | MySQL 5.7 / 8.0（InnoDB、utf8mb4），或 SQLite 3.8+ |
| Web 服务器 | Apache、Nginx、LiteSpeed、IIS、Caddy 均可 |

> 不需要 Composer，不需要 Node，不需要 Redis。`openssl` 用于发布包与插件包签名校验。

## 快速开始

1. 下载发布包，解压到站点根目录（或子目录），保持目录结构不变。
2. 浏览器打开 `http://你的域名/install.php`。
3. 按向导完成：环境检测 → 数据库 → 站点与管理员 → 安装自检。
4. 安装完成后**删除或改名 `install.php`**，登录 `/admin/login`。

数据库不存在时，安装器会尝试自动建库；没有建库权限时，先在主机面板建好库再填写即可。SQLite 模式无需数据库账号，数据文件会落在 Web 根之外。

## 目录结构

```text
index.php              唯一入口
install.php            安装向导（装完请删除或改名）
router.php             PHP 内置服务器路由（本地开发）
app/                   内核：Core / Models / Controllers / views
themes/default/        默认主题
plugins/markdown/      Markdown 编辑器（默认启用）
plugins/richtext/      富文本编辑器（按需启用）
storage/               缓存、上传、日志、伪静态规则
docs/                  部署与使用文档
config.php             站点配置（安装时生成，勿提交公开仓库）
```

## SEO 设计

SvelCMS 不把 SEO 交给插件，而是作为内核能力处理：

- 每篇文章输出独立标题、description、canonical 与 Open Graph/JSON-LD。
- 分类、标签、搜索、分页均有明确的 canonical 与 `rel=prev/next`。
- 自动生成 `sitemap.xml`、`robots.txt`、RSS；支持在后台追加 robots 规则。
- 修改 slug 时写入 301 映射，避免旧链接失效和重复内容。
- 页面缓存只缓存可公开内容，登录态、查询参数与异常页不进入缓存。

## 安全设计

- 后台登录限速与账号级锁定，支持 TOTP 两步验证与恢复码。
- 注册邮箱验证、站点绑定归属验证、市场 API Token 哈希存储。
- 全站 CSRF 校验；后台操作写入审计日志，可按页查看并自动清理过期记录。
- 上传目录禁止执行 PHP；模板、配置、密钥、会话、日志与安装包不可直接访问。
- CMS 与市场发布包使用 Ed25519 签名；升级前校验 SHA-256 与签名，失败自动回滚。

## 应用市场与扩展

SvelCMS 市场是独立站点，可单独部署到另一台服务器：

- 插件/主题分发、版本管理、下载统计与开发者入驻。
- CMS 后台检查更新，支持 stable / beta 通道与手动/自动升级。
- 发布包在上传时完成签名，CMS 侧验签后才允许解压覆盖。

市场地址：<https://app.svelcms.com>

## 本地开发

```bash
# 启动内置服务器（需要 PHP 8.1+）
php -S 127.0.0.1:8080 router.php

# 语法检查
find app themes plugins -name '*.php' -print0 | xargs -0 -n1 php -l
```

项目不使用构建工具。修改主题或插件后直接刷新页面；生产环境建议在后台清理页面缓存。

## 发布与升级

- 发布包为单个 zip，解压后即为站点根目录结构，不额外套一层目录。
- CMS 版本号位于 `app/bootstrap.php` 的 `SVEL_VERSION`；市场版本号位于 `shichang/app/bootstrap.php` 的 `MKT_VERSION`。
- 发布包签名使用 Ed25519，私钥不入库、不入仓；CMS 只内置公钥用于验签。
- 升级过程带健康检查与失败回滚，数据库结构变更采用向后兼容迁移。

## 贡献

欢迎提交 Issue 与 Pull Request。请保持以下原则：

- 不引入 Composer/npm 运行时依赖；确需新增能力时优先做成可选插件。
- 不破坏零依赖部署与共享虚拟主机兼容性。
- 涉及安全、升级、缓存与 SEO 的改动，请附带可复现的验证步骤。
- 提交前至少通过 `php -l` 语法检查与相关页面的手工回归。

## License

本项目使用 [MIT License](LICENSE)。  
Copyright © 2026 SvelCMS contributors.