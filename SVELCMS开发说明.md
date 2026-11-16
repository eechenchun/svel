# SvelCMS 完整开发说明

> **本文件是 SvelCMS 项目的唯一开发依据。**
> 读取本文件后，按第 20 节的阶段顺序施工，每个阶段结束需通过第 20 节对应的验收条目。
> 文件版本：v1.2　编写日期：2026-11-16　适用程序版本：1.2.1
>
> **v1.1 修订**：数据库改为「MySQL 默认 + SQLite 3 次选」双驱动；slug 改为可修改并写入 301 跳转；后台编辑器改为内置 Markdown 三栏（源码/预览/目录，滚动同步），富文本编辑器外置为插件，内容格式双模式（markdown / html）由内核内置渲染支持。
>
> **v1.2 修订**：后台设置改为六个选项卡（基本 / 全局 / SEO / 附件 / 伪静态 / 后台），伪静态不再单独占菜单；新增系统升级：市场侧管理 CMS 版本，站点侧检查更新、手动升级、自动升级（带通道、备份与失败回滚）。

---

## 0. 给 DuMate 的执行说明（先读这段）

1. 本文件是完整规格，**不需要再向外索取需求**。所有未在本文件出现的功能，默认不做。
2. 按第 20 节的阶段推进，**一次只做一个阶段**，做完列出改动文件与自测结果，人工验收后再进入下一阶段。
3. 严格遵守第 3 节的硬约束。任何一条被违反，视为不合格交付。
4. 代码放在当前项目根目录下，目录结构按第 5 节。**不要新建与本文件冲突的目录**。
5. 处于「待确认」状态的内容见第 22 节，遇到时按其中给出的默认值执行，不要自行发挥。
6. 交付验收以第 20 节的验收清单为准，逐条核对，不得跳过。
7. 本文件是**单文件规格**，已自包含全部信息。不要在实现过程中把它拆成多个文档，也不要另建需求文档或重新分析需求。

---

## 1. 项目定义

**SvelCMS**（中文名：**苗条 CMS**）是一个超轻量、SEO 优先的开源mit协议的博客程序。

一句话概括：**解压上传、运行 `install.php`、装完即用，默认不装任何插件和第三方主题就是一个完整可被搜索引擎收录的博客站。主打轻量和强SEO**

三个必须成立的定位：

1. **零插件零主题即可运行**。开箱状态下前台、后台、SEO 全部可用，不依赖任何外部组件。
2. **一切扩展靠插件**。内核只保留钩子位与最小必要功能，其余功能一律外置为插件。
3. **上传即装**。Apache 与 Nginx 下均自动完成配置，站长不需要编辑任何服务器配置文件。

明确不做：多站点、会员体系、电商、评论审核中台、支付、分成、SaaS 化。

**使用者定位：个人站长，非专业运维。** 程序必须满足三个「自己能搞定」：

1. **装**：上传压缩包、浏览器打开 `install.php`、按提示填三项信息即可完成，不需要命令行，不需要改服务器配置。
2. **维护**：改标题、换主题、装插件全部在后台点选完成；出问题能在后台看到明确错误提示与日志文件路径。
3. **二次开发**：源码按第 5 节目录组织，一个功能基本落在一个文件里，不依赖框架文档；新增一张表、加一个后台页面的做法在第 7 节与第 12 节有现成模板可照抄。

英文名与中文名并存使用：

| 场景                                     | 用名                    |
| ---------------------------------------- | ----------------------- |
| 代码目录、包名、数据库前缀、仓库名、域名 | `svelcms`（全小写连写） |
| 中文界面、官网标题、宣传物料             | 苗条 CMS                |
| 程序内部常量、版本号、日志标识           | `SVELCMS`               |

---

## 2. 命名规范

| 对象       | 规则                     | 示例                     |
| ---------- | ------------------------ | ------------------------ |
| 命名空间   | `Svel\`                  | `Svel\Core\Router`       |
| 类名       | 大驼峰                   | `PostController`         |
| 方法/函数  | 小驼峰                   | `getBySlug()`            |
| 常量       | 全大写下划线             | `SVEL_VERSION`           |
| 数据表     | 小写下划线，单数         | `post`、`post_tag`       |
| 表字段     | 小写下划线               | `published_at`           |
| 配置键     | 点分小写                 | `site.title`             |
| 钩子名     | 小写下划线               | `the_content`            |
| 数据库前缀 | 默认 `svel_`，安装时可改 | `svel_post`              |
| 文件       | 大驼峰类文件、小写模板   | `Router.php`、`post.php` |

---

## 3. 硬约束（违反即不合格）

**依赖**

- 禁止 `composer require`，禁止引入任何第三方 PHP 包
- 禁止引入 jQuery、Vue、React、Tailwind、Bootstrap、Alpine 等前端库
- 允许使用 PHP 内置扩展：`pdo_mysql` 或 `mysqli`（MySQL 模式必需）、`pdo_sqlite` 或 `sqlite3`（SQLite 模式必需）、`mbstring`、`json`、`zip`、`openssl`（插件签名校验必需）、`gd`（仅图片处理时可选）
- 两种数据库驱动都必须实现，由 `config.php` 的 `db.driver` 决定；**禁止只实现其中一种**

**数据与安全**

- 所有 SQL 必须参数化绑定，禁止字符串拼接值
- 所有输出到 HTML 的变量必须经 `e()` 转义
- 密码必须用 `password_hash()` / `password_verify()`
- 后台所有写操作必须校验 CSRF 令牌
- 文件上传必须白名单扩展名、重命名、禁止 php 可执行后缀
- 插件包解压必须拒绝 `../`、绝对路径、符号链接

**性能**

- 首页在无缓存、无插件状态下：PHP 执行时间 MySQL < 60ms、SQLite < 30ms，含数据库连接的峰值内存 < 8MB
- 缓存命中直接输出：< 15ms
- 核心文件数 < 200 个，核心代码 < 15000 行（不含默认主题 CSS 与后台编辑器 JS）
- 零插件状态前台请求只读 6 张表；安装器一次性建 9 张表
- 首屏 HTML 输出 < 20KB

**代码**

- 所有文件 `<?php` 开头不写结束标签（避免额外空白输出）
- 统一 `declare(strict_types=1)`
- 不写 `var_dump`、`print_r` 调试残留
- PHP 文件统一 UTF-8 无 BOM，缩进 4 空格

---

## 4. 技术栈

| 项目 | 决定                                                         | 说明                                      |
| ---- | ------------------------------------------------------------ | ----------------------------------------- |
| 语言 | PHP 8.1+（兼容 8.4）                                         | 不使用 8.2 以上专有语法，保证虚拟主机兼容 |
| 存储 | MySQL 5.7+ / 8.0 **默认**（InnoDB、utf8mb4）；SQLite 3.8+ **次选** | 同一 `Db` 层双驱动；安装器默认走 MySQL，无 MySQL 扩展或连接失败时提示改用 SQLite |
| 依赖 | 零第三方                                                     | 自写 autoload，spl_autoload_register      |
| 框架 | 无                                                           | 路由、模板、数据层全部自写                |
| 前端 | 原生 HTML + 单 CSS                                           | 零 JS 框架，必要交互用原生 JS（< 3KB）    |
| 缓存 | 文件缓存                                                     | 静态 HTML 落盘，命中直接输出              |
| 部署 | Apache / Nginx / 宝塔 / LiteSpeed / IIS / Caddy / 内置服务器 | 见第 16 节                                |

`config.php` 结构（安装器生成）：

```php
<?php
return [
    // MySQL 模式（默认）
    'db' => [
        'driver'    => 'mysql',        // mysql | sqlite
        'host'      => '127.0.0.1',    // 或 localhost（走 unix socket，更快）
        'port'      => 3306,
        'database'  => 'svelcms',
        'username'  => 'svelcms',
        'password'  => '数据库密码',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => 'svel_',
    ],
    // SQLite 模式（次选）：把上面 'db' 整体替换为下面三行
    // 'db' => [
    //     'driver' => 'sqlite',
    //     'file'   => '/absolute/path/outside/webroot/svel.db',
    //     'prefix' => 'svel_',
    // ],
    'site' => [
        'url'      => 'https://example.com',
        'base'     => '/',          // 子目录部署时为 '/blog/'
        'timezone' => 'Asia/Shanghai',
    ],
    'storage' => [
        'dir' => '/absolute/path/svel-data',  // 数据目录（web 不可达），存 redirect.php 与临时文件
    ],
    'url_mode'   => 1,             // 1=L1 干净URL 2=L2 PATH_INFO 3=L3 查询串
    'theme'      => 'default',
    'debug'      => false,
    'secret'     => '安装时生成的 64 位随机串',
    'market_url' => 'https://example.com/market/',
];
```

---

## 5. 目录结构

```text
项目根目录/
├── SVELCMS开发说明.md         # 本文件
├── index.php                  # 唯一入口（前端控制器）
├── install.php                # 安装器，装完自动失效
├── router.php                 # PHP 内置服务器路由（本地预览）
├── .htaccess                  # Apache 规则（安装器生成）
├── web.config                 # IIS 规则（安装器生成）
├── install.lock               # 安装完成标记（内容为安装时间与版本）
├── config/
│   └── config.php             # 站点配置（安装生成）
├── app/
│   ├── bootstrap.php          # 引导：autoload、配置、DB、插件、路由
│   ├── helpers.php            # 全局函数
│   ├── assets/
│   │   ├── admin.css          # 后台样式（含三栏编辑器布局）
│   │   └── editor.js          # 内置 Markdown 编辑器：预览、目录、滚动同步
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Db.php
│   │   ├── View.php
│   │   ├── Hook.php
│   │   ├── Plugin.php
│   │   ├── Theme.php
│   │   ├── Cache.php
│   │   ├── Auth.php
│   │   ├── Csrf.php
│   │   ├── Seo.php
│   │   ├── Markdown.php       # Markdown → HTML（内核内置，编辑预览/前台渲染共用）
│   │   ├── Redirect.php       # slug 变更后的 301 映射读写与查找
│   │   ├── Installer.php      # 安装逻辑，被 install.php 调用
│   │   └── Market.php         # 市场客户端（下载、验签、安装）
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── PostController.php
│   │   ├── PageController.php
│   │   ├── CategoryController.php
│   │   ├── TagController.php
│   │   ├── SearchController.php
│   │   ├── FeedController.php
│   │   ├── SitemapController.php
│   │   ├── RobotsController.php
│   │   └── Admin/
│   │       ├── LoginController.php
│   │       ├── DashboardController.php
│   │       ├── PostController.php
│   │       ├── CategoryController.php
│   │       ├── TagController.php
│   │       ├── OptionController.php
│   │       ├── PluginController.php
│   │       ├── ThemeController.php
│   │       ├── MarketController.php
│   │       ├── DeveloperController.php
│   │       ├── PreviewController.php  # 编辑页 Markdown 实时预览接口
│   │       ├── UploadController.php   # 正文插图上传接口
│   │       └── RewriteController.php
│   └── Models/
│       ├── Post.php
│       ├── Category.php
│       ├── Tag.php
│       ├── Option.php
│       ├── User.php
│       ├── PluginModel.php
│       ├── ThemeModel.php
│       └── DeveloperApp.php
├── themes/
│   └── default/
│       ├── theme.json
│       ├── functions.php
│       ├── layout.php
│       ├── index.php
│       ├── post.php
│       ├── page.php
│       ├── category.php
│       ├── tag.php
│       ├── search.php
│       ├── 404.php
│       ├── partials/
│       │   ├── header.php
│       │   ├── footer.php
│       │   ├── sidebar.php
│       │   └── pagination.php
│       └── assets/
│           └── style.css
├── plugins/                   # 插件安装目录（初始为空，保留 .gitkeep）
├── market/                    # 市场服务端（可独立部署，也可同域）
│   ├── index.php
│   ├── config.php
│   └── data/
│       ├── items.json         # 插件与主题清单
│       ├── packages/          # 插件包 zip
│       └── developers.json    # 入驻申请
├── storage/
│   ├── cache/                 # 页面静态缓存（写临时文件后 rename，避免读到半截页面）
│   ├── uploads/               # 上传文件（必须禁执行 PHP）
│   ├── rewrite/               # 生成的伪静态规则片段
│   ├── backups/               # 升级前内核备份（cms-版本-时间.zip）
│   └── logs/                  # 错误日志
└── docs/
    ├── deploy.md              # 部署说明（交付时提供）
    └── usage.md               # 使用说明（交付时提供）
```

数据目录选址（安装器决定，见第 15 节）：

- 优先：`站点根目录之外` 的 `svel-data/`（web 不可达）
- 回退：站内 `storage/data-{随机6位}/`
- 该目录存放：SQLite 模式的 `svel.db`、`redirect.php`（slug 旧地址映射）、安装与升级临时文件。MySQL 模式下不存数据库，但仍用于 `redirect.php` 与临时文件

---

## 6. 模块划分

| 模块       | 职责                                          | 关键文件                                   | 不做什么               |
| ---------- | --------------------------------------------- | ------------------------------------------ | ---------------------- |
| 入口与引导 | 单入口、autoload、配置加载、错误处理          | index.php、bootstrap.php                   | 不写业务逻辑           |
| 路由       | 三档 URL 归一化、路由匹配、分发               | Router.php、Request.php                    | 不做 REST 全自动映射   |
| 数据层     | MySQL / SQLite 双驱动连接、参数化查询、事务   | Db.php                                     | 不做 ORM、不做迁移框架 |
| 模板       | 渲染、主题回退、片段引入                      | View.php、Theme.php                        | 不做模板引擎、不做编译 |
| 内容格式   | Markdown 解析、HTML 白名单过滤、渲染结果缓存  | Markdown.php                               | 不做完整 CommonMark 全实现、不做 WYSIWYG |
| 后台编辑   | 三栏编辑器、实时预览、目录、滚动同步          | editor.js、PreviewController.php           | 不做可视化拖拽、不做协同编辑 |
| 钩子       | 注册、排序、执行、按需加载                    | Hook.php、Plugin.php                       | 不做事件总线、不做 DI  |
| 前台       | 首页、文章、分类、标签、搜索、页面、404       | Controllers 前台部分                       | 不做评论、不做会员     |
| SEO        | TDK、canonical、JSON-LD、sitemap、robots、RSS | Seo.php、SitemapController、FeedController | 不依赖插件             |
| 缓存       | 页面静态化、失效                              | Cache.php                                  | 不做 Redis/Memcached   |
| 后台       | 登录、内容、设置（六选项卡）、应用市场、系统升级 | Controllers/Admin                       | 不做多角色权限体系     |
| 应用市场   | 清单拉取、下载、验签、安装、启用              | Market.php、MarketController               | 不做支付、不做评分     |
| 安装器     | 环境体检、选址、探测、建库、自检、上锁        | Installer.php、install.php                 | 安装不覆盖已有数据     |
| 升级       | 检查版本、下载校验、备份、覆盖内核、补表结构  | Upgrader.php、UpgradeController            | 不动主题、插件与数据   |

模块间依赖方向（禁止反向依赖）：

```
入口 → 路由 → 控制器 → 模型 → 数据层
         ↓        ↓
       钩子 ← 插件
         ↓
       模板 ← 主题
```

---

## 7. 数据表设计

共 9 张表，零插件状态前台请求只读前 6 张。前缀默认 `svel_`，建表时拼接。

字段类型写成**逻辑类型**，由 7.10 的映射表落地为 MySQL / SQLite 各自的物理类型。时间一律存 **INT 无符号 Unix 秒（UTC）**，不用 `DATETIME`，避免两种库与时区双重换算。

### 7.1 post（文章与独立页面）

| 字段           | 类型                         | 说明                                                       |
| -------------- | ---------------------------- | ---------------------------------------------------------- |
| id             | INT UNSIGNED PK AUTO_INCREMENT | 主键                                                       |
| title          | VARCHAR(255) NOT NULL        | 标题                                                       |
| slug           | VARCHAR(191) NOT NULL UNIQUE | URL 标识，**可修改**；修改时把旧地址写入 301 映射（见 11.1） |
| content        | MEDIUMTEXT                   | 正文，格式由 `content_format` 决定                          |
| content_format | VARCHAR(10) DEFAULT markdown | `markdown`（内置编辑器写入）/ `html`（富文本插件写入）      |
| excerpt        | VARCHAR(500)                 | 摘要，空则从正文取纯文本截取                                |
| cover          | VARCHAR(255)                 | 封面图路径                                                 |
| status         | VARCHAR(10) DEFAULT draft    | `draft` / `publish`                                        |
| type           | VARCHAR(10) DEFAULT post     | `post` / `page`                                            |
| category_id    | INT UNSIGNED DEFAULT 0       | 分类 ID，0 为未分类                                        |
| author_id      | INT UNSIGNED DEFAULT 0       | 作者 ID                                                    |
| views          | INT UNSIGNED DEFAULT 0       | 浏览量                                                     |
| is_top         | TINYINT DEFAULT 0            | 是否置顶 0/1                                               |
| seo_title      | VARCHAR(255)                 | 自定义 title，空则用标题                                   |
| seo_desc       | VARCHAR(255)                 | 自定义 description                                         |
| seo_keywords   | VARCHAR(255)                 | 自定义 keywords                                            |
| created_at     | INT UNSIGNED                 | 创建时间戳（UTC 秒）                                       |
| updated_at     | INT UNSIGNED                 | 更新时间戳                                                 |
| published_at   | INT UNSIGNED                 | 发布时间戳，草稿为 0                                       |

索引：`UNIQUE(slug)`、`(status, published_at)`、`(category_id)`、`(type)`

`slug` 长度取 191 是为了让 `utf8mb4` 下的唯一索引不超过 MySQL 5.7 的 767 字节索引前缀限制，两种库都用同一个长度，不做分支。

### 7.2 category

| 字段        | 类型                           | 说明                           |
| ----------- | ------------------------------ | ------------------------------ |
| id          | INT UNSIGNED PK AUTO_INCREMENT | 主键                           |
| name        | VARCHAR(100) NOT NULL          | 名称                           |
| slug        | VARCHAR(191) NOT NULL UNIQUE   | URL 标识，可修改，改后旧地址 301 |
| description | VARCHAR(255)                   | 描述，用于分类页 description   |
| parent_id   | INT UNSIGNED DEFAULT 0         | 父分类，0 为顶级               |
| sort        | INT DEFAULT 0                  | 排序值，小的在前               |

索引：`UNIQUE(slug)`、`(sort)`

### 7.3 tag

| 字段 | 类型                           | 说明                             |
| ---- | ------------------------------ | -------------------------------- |
| id   | INT UNSIGNED PK AUTO_INCREMENT | 主键                             |
| name | VARCHAR(100) NOT NULL          | 名称                             |
| slug | VARCHAR(191) NOT NULL UNIQUE   | URL 标识，可修改，改后旧地址 301 |

### 7.4 post_tag

| 字段    | 类型         | 说明    |
| ------- | ------------ | ------- |
| post_id | INT UNSIGNED | 文章 ID |
| tag_id  | INT UNSIGNED | 标签 ID |

主键：`PRIMARY KEY(post_id, tag_id)`；索引：`(tag_id)`。两种库都建 `ON DELETE CASCADE` 外键（SQLite 需连接时执行 `PRAGMA foreign_keys=ON`），删除文章或标签时不留孤儿行。

### 7.5 option

| 字段  | 类型                    | 说明                              |
| ----- | ----------------------- | --------------------------------- |
| name  | VARCHAR(191) PK         | 配置键                            |
| value | MEDIUMTEXT              | 配置值（字符串存储，必要时 JSON） |

必填键（安装时写入）：

| 键                  | 默认值                                                                                                                                                                                               | 说明             |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| `site.title`        | 苗条 CMS                                                                                                                                                                                             | 站点名           |
| `site.description`  | 苗条 CMS（SvelCMS）是一款超轻量、强SEO 开源MIT协议的博客程序，安装程序即可使用。默认不装任何插件与第三方主题，本身就是一个完整的博客站，访问快、结构干净；需要扩展功能时再装插件，内核始终保持轻量。 | 站点描述         |
| `site.keywords`     | 苗条CMS,SvelCMS,轻量博客程序,SEO博客,开源博客源码                                                                                                                                                    | 站点关键词       |
| `site.icp`          | 空                                                                                                                                                                                                   | 备案号           |
| `site.per_page`     | 10                                                                                                                                                                                                   | 每页条数         |
| `site.theme`        | default                                                                                                                                                                                              | 当前主题         |
| `site.installed_at` | 时间戳                                                                                                                                                                                               | 安装时间         |
| `site.version`      | 1.0.0                                                                                                                                                                                                | 程序版本         |
| `post.permalink`    | `/post/{slug}.html`                                                                                                                                                                                  | 文章 URL 规则    |
| `cache.enable`      | 1                                                                                                                                                                                                    | 是否启用页面缓存 |
| `cache.ttl`         | 3600                                                                                                                                                                                                 | 缓存过期秒数     |

### 7.6 user

| 字段          | 类型                           | 说明                   |
| ------------- | ------------------------------ | ---------------------- |
| id            | INT UNSIGNED PK AUTO_INCREMENT | 主键                   |
| username      | VARCHAR(64) NOT NULL UNIQUE    | 登录名                 |
| password_hash | VARCHAR(255) NOT NULL          | 密码哈希               |
| nickname      | VARCHAR(64)                    | 昵称                   |
| email         | VARCHAR(120)                   | 邮箱                   |
| role          | VARCHAR(20) DEFAULT admin      | `admin`（v1 只有一种） |
| created_at    | INT UNSIGNED                   | 创建时间               |

### 7.7 plugin

| 字段         | 类型                          | 说明                                                                 |
| ------------ | ----------------------------- | -------------------------------------------------------------------- |
| slug         | VARCHAR(64) PK                | 插件标识                                                             |
| name         | VARCHAR(100)                  | 显示名                                                               |
| version      | VARCHAR(20)                   | 版本                                                                 |
| status       | TINYINT DEFAULT 0             | 0 停用 / 1 启用                                                      |
| hooks        | TEXT                          | JSON，**钩子名 → `[{file, priority}]` 列表**，支持多插件挂同一钩子   |
| source       | VARCHAR(20)                   | `market` / `upload` / `builtin`                                      |
| priority     | INT DEFAULT 10                | 该插件未在 plugin.json 声明单个钩子优先级时的默认值                  |
| checksum     | CHAR(64)                      | 包 SHA-256                                                           |
| installed_at | INT UNSIGNED                  | 安装时间                                                             |

### 7.8 theme

| 字段         | 类型              | 说明                  |
| ------------ | ----------------- | --------------------- |
| slug         | VARCHAR(64) PK    | 主题标识              |
| name         | VARCHAR(100)      | 显示名                |
| version      | VARCHAR(20)       | 版本                  |
| status       | TINYINT DEFAULT 0 | 0 未启用 / 1 当前启用 |
| installed_at | INT UNSIGNED      | 安装时间              |

### 7.9 developer_app

| 字段       | 类型                           | 说明                                |
| ---------- | ------------------------------ | ----------------------------------- |
| id         | INT UNSIGNED PK AUTO_INCREMENT | 主键                                |
| name       | VARCHAR(100)                   | 开发者名称                          |
| email      | VARCHAR(120)                   | 邮箱                                |
| site       | VARCHAR(255)                   | 个人站点                            |
| intro      | TEXT                           | 简介                                |
| status     | VARCHAR(20) DEFAULT pending    | `pending` / `approved` / `rejected` |
| created_at | INT UNSIGNED                   | 提交时间                            |

### 7.10 建表与类型映射

安装器按 7.1–7.9 的逻辑类型生成 DDL，一次性执行。**注意两种库的事务能力不同**：SQLite 包在事务里、失败直接回滚；MySQL 的 DDL 会隐式提交，事务回滚不了建表，失败时按「已建表清单逆序 DROP」清理（实现见 15.6）。

| 逻辑类型                     | MySQL 5.7+                        | SQLite 3.8+                     |
| ---------------------------- | --------------------------------- | ------------------------------- |
| `INT UNSIGNED PK AUTO_INCREMENT` | `INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY` | `INTEGER PRIMARY KEY AUTOINCREMENT` |
| `INT UNSIGNED` / `INT`       | `INT UNSIGNED` / `INT`            | `INTEGER`                       |
| `TINYINT`                    | `TINYINT`                         | `INTEGER`                       |
| `VARCHAR(n)`                 | `VARCHAR(n)`                      | `TEXT`（长度只做应用层校验）    |
| `TEXT` / `MEDIUMTEXT`        | `TEXT` / `MEDIUMTEXT`             | `TEXT`                          |
| `CHAR(64)`                   | `CHAR(64)`                        | `TEXT`                          |

MySQL 建表固定 `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`；字段、表名一律用反引号包裹，SQLite 用双引号，引号字符由 `Db` 按驱动决定，业务代码不写引号。

MySQL 模式建表前校验连接账号权限：需具备 `CREATE / ALTER / INDEX / INSERT / UPDATE / DELETE / SELECT`；无 `CREATE DATABASE` 权限时只建表不建库，并在页面提示「请先在面板创建数据库并授权」。

---

## 8. 路由与三档 URL

### 8.1 三档定义

| 档位 | URL 形态                       | 依赖           | 判定     |
| ---- | ------------------------------ | -------------- | -------- |
| L1   | `/post/hello.html`             | 服务器重写生效 | 探测通过 |
| L2   | `/index.php/post/hello.html`   | PHP PATH_INFO  | 探测通过 |
| L3   | `/index.php?p=post&slug=hello` | 无             | 兜底     |

### 8.2 归一化算法（核心）

入口统一把任何档位的请求归一化成一个内部 path，路由只认 path：

1. 若 `$_SERVER['PATH_INFO']` 非空，取它作为 path（L2）。
2. 否则取 `REQUEST_URI` 的路径部分，剥离 `base` 前缀与 `index.php`，作为 path（L1）。
3. 若 path 为空或 `/`，且存在 `?p=` 参数，则按 `p` 与 `slug`/`id` 合成虚拟 path（L3）：`p=post&slug=x` → `/post/x.html`。
4. 统一补前导 `/`，去掉重复斜杠，去掉尾部 `/`（根除外）。

`base` 前缀由 `Request` 自动解析：取 `dirname($_SERVER['SCRIPT_NAME'])`，子目录部署时为 `/blog`，根目录为 ``。

### 8.3 路由表

| 方法     | 路径                        | 控制器                      | 说明                  |
| -------- | --------------------------- | --------------------------- | --------------------- |
| GET      | `/`                         | HomeController@index        | 首页                  |
| GET      | `/page/{n}`                 | HomeController@index        | 首页分页，n≥2         |
| GET      | `/post/{slug}.html`         | PostController@show         | 文章页                |
| GET      | `/{slug}.html`              | PageController@show         | 独立页面（type=page） |
| GET      | `/category/{slug}`          | CategoryController@index    | 分类页                |
| GET      | `/category/{slug}/page/{n}` | CategoryController@index    | 分类分页              |
| GET      | `/tag/{slug}`               | TagController@index         | 标签页                |
| GET      | `/tag/{slug}/page/{n}`      | TagController@index         | 标签分页              |
| GET      | `/search`                   | SearchController@index      | 搜索，参数 `q`        |
| GET      | `/feed`                     | FeedController@index        | RSS 2.0               |
| GET      | `/sitemap.xml`              | SitemapController@index     | 站点地图              |
| GET      | `/robots.txt`               | RobotsController@index      | 爬虫规则              |
| GET      | `/admin`                    | DashboardController@index   | 后台首页              |
| GET/POST | `/admin/login`              | LoginController             | 登录                  |
| GET      | `/admin/logout`             | LoginController@logout      | 退出                  |
| GET      | `/admin/post`               | Admin\PostController@index  | 文章列表              |
| GET/POST | `/admin/post/edit`          | Admin\PostController@edit   | 新建/编辑             |
| POST     | `/admin/post/delete`        | Admin\PostController@delete | 删除                  |
| POST     | `/admin/preview`            | Admin\PreviewController     | Markdown 实时预览，返回 HTML 片段 |
| POST     | `/admin/upload`             | Admin\UploadController      | 正文插图上传，返回 JSON（url、宽高） |
| GET/POST | `/admin/category`           | Admin\CategoryController    | 分类管理              |
| GET/POST | `/admin/tag`                | Admin\TagController         | 标签管理              |
| GET/POST | `/admin/setting`            | Admin\SettingController     | 站点设置（六选项卡）  |
| POST     | `/admin/setting/rewrite`    | Admin\SettingController@rewrite | 伪静态重新检测与切档 |
| GET      | `/admin/upgrade`            | Admin\UpgradeController     | 系统升级              |
| POST     | `/admin/upgrade/check`      | Admin\UpgradeController@check | 检查更新             |
| POST     | `/admin/upgrade/run`        | Admin\UpgradeController@upgrade | 执行升级           |
| POST     | `/admin/upgrade/toggle`     | Admin\UpgradeController@toggle | 自动升级与通道     |
| GET/POST | `/admin/plugin`             | Admin\PluginController      | 插件管理              |
| GET/POST | `/admin/theme`              | Admin\ThemeController       | 主题管理              |
| GET/POST | `/admin/market`             | Admin\MarketController      | 应用市场              |
| GET/POST | `/admin/developer`          | Admin\DeveloperController   | 开发者入驻            |

未匹配路由返回 404 页面并输出 **HTTP 404 状态码**。

### 8.4 URL 生成规则

所有链接必须用 `url($path)` 生成，禁止在模板里硬编码 `/`：

- L1：`/post/hello.html`
- L2：`/index.php/post/hello.html`
- L3：`/index.php?p=post&slug=hello`

`url()` 内部读 `url_mode` 与 `base` 自动切换，模板无需关心当前档位。

---

## 9. 钩子系统

### 9.1 两种钩子

- **filter**：值进、值出，可被多个插件依次修改
- **action**：某时机插入执行，不接收返回值

不做事件总线、不做 DI 容器、不做中间件链。

### 9.2 接口签名

```php
Hook::addFilter(string $name, callable $fn, int $priority = 10, int $args = 1): void;
Hook::addAction(string $name, callable $fn, int $priority = 10, int $args = 1): void;
Hook::filter(string $name, mixed $value, mixed ...$extra): mixed;
Hook::do(string $name, mixed ...$args): void;
Hook::hasFilter(string $name): bool;
Hook::registered(string $name): array;
```

执行顺序：priority 升序，同 priority 按注册顺序。

### 9.3 按需加载（核心机制）

1. 插件启用时，扫描插件目录下的 `plugin.json`，读取其声明的 `hooks`。两种写法都支持：数组 `["the_content"]`（优先级取插件 `priority` 字段，默认 10），或对象 `{"the_content": 20}`（逐钩子指定优先级）。
2. 归一化成 **「钩子名 → `[{plugin, file, priority}]` 列表」**，写入 `plugin.hooks` 字段，并缓存到 `storage/cache/hooks.php`（`<?php return [...]` 形式，被直接访问也不输出内容）。
3. 多个插件挂同一钩子时，同一钩子的列表按 `priority` 升序排；`priority` 相同则按插件启用先后排。**不允许后装的插件覆盖先装的插件**。
4. 请求执行到某钩子时，取该钩子的列表，逐个 `include_once` 尚未加载的入口文件，并按列表顺序调用回调。
5. **当前请求未触发的钩子，其插件入口文件一个字节都不读。**

推论：装 50 个插件、首页只触发 3 个钩子，就只加载 3 个文件（一个插件挂多个钩子也只加载一次）。

### 9.4 钩子清单

**A 级：每请求必触发（插件文档需注明「慎挂、禁止查库」）**

| 钩子               | 类型   | 参数                          | 返回   | 触发时机         |
| ------------------ | ------ | ----------------------------- | ------ | ---------------- |
| `site_title`       | filter | string $title                 | string | 渲染 head 前     |
| `site_description` | filter | string $desc                  | string | 渲染 head 前     |
| `nav_items`        | filter | array $items                  | array  | 渲染导航前       |
| `body_class`       | filter | string $class                 | string | 渲染 body 标签前 |
| `head_meta`        | filter | string $html                  | string | head 结束前      |
| `footer_html`      | filter | string $html                  | string | body 结束前      |
| `init`             | action | 无                            | 无     | 路由分发前       |
| `before_render`    | action | string $template, array $data | 无     | 模板渲染前       |
| `after_render`     | action | string $html                  | 无     | 渲染完成后       |

**B 级：仅特定路由触发**

| 钩子               | 类型   | 参数                         | 返回   | 触发时机                |
| ------------------ | ------ | ---------------------------- | ------ | ----------------------- |
| `the_title`        | filter | string $title, array $post   | string | 文章页/列表项           |
| `the_content`      | filter | string $html, array $post    | string | 文章正文输出前          |
| `the_excerpt`      | filter | string $excerpt, array $post | string | 摘要输出前              |
| `post_permalink`   | filter | string $url, array $post     | string | 生成文章链接时          |
| `posts_per_page`   | filter | int $n                       | int    | 列表分页计算前          |
| `page_title`       | filter | string $title                | string | 页面 title 组装前       |
| `page_description` | filter | string $desc                 | string | 页面 description 组装前 |
| `canonical_url`    | filter | string $url                  | string | canonical 输出前        |
| `sitemap_urls`     | filter | array $urls                  | array  | sitemap.xml 生成前      |
| `feed_items`       | filter | array $items                 | array  | RSS 生成前              |
| `robots_txt`       | filter | string $txt                  | string | robots.txt 输出前       |
| `cache_key`        | filter | string $key, array $ctx      | string | 读写缓存前              |
| `search_results`   | filter | array $posts, string $q      | array  | 搜索结果返回前          |
| `admin_editor`     | filter | string $html, array $post    | string | 后台编辑区输出前；富文本插件在此替换编辑器，内置 Markdown 编辑器是默认返回值 |
| `admin_editor_assets` | filter | string $html             | string | 后台编辑页 head 内追加脚本与样式，供编辑器插件注入自己的资源 |

**C 级：仅特定事件触发**

| 钩子                 | 类型   | 参数                      | 触发时机   |
| -------------------- | ------ | ------------------------- | ---------- |
| `post_saved`         | action | array $post, bool $isNew  | 文章保存后 |
| `post_deleted`       | action | int $id                   | 文章删除后 |
| `category_saved`     | action | array $category           | 分类保存后 |
| `category_deleted`   | action | int $id                   | 分类删除后 |
| `upload_completed`   | action | string $path, array $meta | 文件上传后 |
| `plugin_activated`   | action | string $slug              | 插件启用后 |
| `plugin_deactivated` | action | string $slug              | 插件停用后 |
| `theme_activated`    | action | string $slug              | 主题切换后 |
| `admin_init`         | action | 无                        | 进入后台时 |
| `install_completed`  | action | array $config             | 安装成功时 |

### 9.5 版本策略

- 钩子一经发布，**名称与参数顺序永不改变**
- 若语义必须变更，新增 `{name}_v2`，旧钩子保留并继续触发
- `plugin.json` 声明 `requires_hook_version`，内核在启用时校验，不满足则拒绝启用并给出明确提示

---

## 10. 主题系统

### 10.1 结构

主题 = 目录 + `theme.json` + PHP 模板 + 静态资源。**不做模板引擎**，PHP 文件本身就是模板，用 `include` + 输出缓冲。

`theme.json` 字段：

```json
{
  "name": "苗条默认主题",
  "slug": "default",
  "version": "1.0.0",
  "author": "SvelCMS",
  "description": "内置默认主题，零依赖，移动端适配",
  "requires": "1.0.0",
  "screenshot": "assets/screenshot.png"
}
```

### 10.2 渲染与回退

渲染顺序：`当前主题模板 → 内核默认主题同名模板`。找不到文件时回退，**不得报错，不得白屏**。

模板可用的全局变量与方法：

- `$site`（站点信息数组）
- `$post`、`$posts`、`$category`、`$tag`、`$pagination`（按页面类型提供）
- `e()`、`url()`、`asset()`、`option()`、`partial()`、`config()`

### 10.3 模板清单

| 文件                      | 用途                        |
| ------------------------- | --------------------------- |
| `layout.php`              | 主框架，内部输出 `$content` |
| `index.php`               | 首页与分页列表              |
| `post.php`                | 文章详情                    |
| `page.php`                | 独立页面                    |
| `category.php`            | 分类列表                    |
| `tag.php`                 | 标签列表                    |
| `search.php`              | 搜索结果                    |
| `404.php`                 | 404 页面                    |
| `partials/header.php`     | head 与页头                 |
| `partials/footer.php`     | 页脚                        |
| `partials/sidebar.php`    | 侧栏                        |
| `partials/pagination.php` | 分页                        |

### 10.4 默认主题设计要求

- 单文件 CSS，< 12KB，不引入外部字体与图标库
- 移动端优先，单列布局，≥ 768px 转双列
- 首屏不依赖 JS；必要 JS 内联且 < 3KB
- 图片统一 `loading="lazy"` 且带宽高属性
- 深色模式用 `prefers-color-scheme` 自动适配

---

## 11. SEO 内核

以下能力**写死在内核**，不提供关闭开关，不由插件实现。

### 11.1 URL

- 文章：`/post/{slug}.html`
- 独立页：`/{slug}.html`
- 分类：`/category/{slug}`
- 标签：`/tag/{slug}`
- 分页：`/page/2` 形式，第一页不出现 `/page/1`
- 全站 URL 小写，slug 只允许 `a-z0-9-`，长度 1–191
- slug 保留字黑名单：`admin`、`post`、`page`、`category`、`tag`、`search`、`feed`、`sitemap`、`robots`、`index`、`install`、`static`；新建与改名命中即拒绝，并提示原因
- slug 生成规则：标题为纯 ASCII 且含字母时取标题生成（转小写、非 `a-z0-9` 转 `-`、合并连续 `-`、去首尾 `-`、截断 191）；标题含中文时**不做拼音转换**（内置拼音词典会直接违背超轻量定位），默认生成 `post-{id}`（独立页 `page-{id}`，分类 `cat-{id}`，标签 `tag-{id}`），拿到自增 ID 后立即回写。后台随时可手改，改完自动 301

**slug 允许修改，旧地址 301。** 文章、独立页、分类、标签四种对象统一走这套规则，保存时若 slug 发生变化：

1. 校验新 slug 唯一（排除自身）且不在保留字内，不通过则保留原值并提示
2. 把「旧路径 → 新路径」写入 301 映射，由 `Redirect` 类落盘到数据目录的 `redirect.php`，**不占表、不塞 option**
3. 删除旧路径与新路径的页面缓存文件，清 sitemap 缓存
4. 旧路径的后续请求返回 **301**，`Location` 指向新地址，**301 不写页面缓存**（只输出响应头，开销极小）
5. canonical、sitemap、RSS、内链一律使用新地址

映射表规则：

- 键为带类型的相对路径（如 `post/old-slug.html`），值为新的相对路径，避免文章与分类同名互相串号
- 链式压缩：A→B 之后 B 又改成 C，立即把 A 的映射改写为 A→C，**永不产生多跳**
- 防循环：写映射前检查目标是否已在链路中，命中则拒绝本次保存
- 写文件用「临时文件 + rename」原子替换；读取只在路由未命中时发生，正常页面零额外 IO
- 映射记录不进 sitemap，不参与任何 SEO 输出，仅作跳转用

### 11.2 TDK 规则

| 页面   | title                          | description                      |
| ------ | ------------------------------ | -------------------------------- |
| 首页   | `站点名 - 站点描述`            | `site.description`               |
| 文章页 | `seo_title` 或 `标题 - 站点名` | `seo_desc` 或自动截取正文 120 字 |
| 分类页 | `分类名 - 站点名`              | 分类 `description` 或自动生成    |
| 标签页 | `标签名 - 站点名`              | 自动生成                         |
| 搜索页 | `搜索：关键词 - 站点名`        | 自动生成，并输出 `noindex`       |
| 404    | `页面不存在 - 站点名`          | 空，输出 `noindex`               |

### 11.3 其他必输出项

- 每页 `<link rel="canonical">`
- 分页页输出 `<link rel="prev">` / `<link rel="next">`
- 首页输出 WebSite + Organization JSON-LD
- 文章页输出 Article JSON-LD（含 headline、datePublished、dateModified、author、image）
- 全站输出 BreadcrumbList JSON-LD
- 输出 Open Graph 与 Twitter Card 基础标签
- `robots.txt` 动态生成，末尾带 sitemap 地址
- `sitemap.xml` 含 `lastmod`；文章超 5000 篇时拆分为索引 + 子地图
- RSS 2.0 输出最近 20 篇

### 11.4 缓存与 SEO 的关系

- 发布或修改文章后，立即失效相关缓存（首页、该文章、所属分类、所属标签、sitemap）
- **slug 变更时额外失效旧路径缓存**：旧路径缓存若未删除，301 会被旧 HTML 顶掉，表现为改完 slug 后旧地址仍返回 200
- 缓存文件直接输出时，仍需保证 HTTP 状态码正确（404 页缓存也必须返回 404）；404 缓存 TTL 取 `cache.ttl` 与 300 秒的较小值
- 缓存不缓存 `?q=` 搜索页与后台页面
- 命中缓存时输出响应头 `X-Svel-Cache: HIT`，未命中为 `MISS`，替代在 HTML 里塞 debug 注释

---

## 12. 后台功能

后台路径统一前缀 `/admin`，所有页面要求登录后访问，未登录跳转 `/admin/login`。

| 模块       | 功能项                                                                     | 验收要点                                                 |
| ---------- | -------------------------------------------------------------------------- | -------------------------------------------------------- |
| 登录       | 账号 + 密码 + CSRF；同 IP 连续失败 5 次锁定 10 分钟                        | 错误密码提示统一为「账号或密码错误」，不暴露账号是否存在 |
| 仪表盘     | 文章总数、草稿数、缓存状态、程序版本、当前 URL 档位、服务器类型            | 数据与实际一致                                           |
| 文章       | 列表（按状态/分类筛选、标题搜索、分页）、新建、编辑、删除、置顶、草稿/发布、修改 slug | 保存后前台立即可见；删除有二次确认；改 slug 后旧地址 301 到新地址 |
| 分类       | 增删改查、排序、父级选择                                                   | 删除分类时，其下文章归入「未分类」，不级联删除文章       |
| 标签       | 增删改查                                                                   | 同名标签自动复用，不重复创建                             |
| 设置       | 六选项卡：基本、全局、SEO、附件、伪静态、后台                       | 各选项卡独立保存并回到原选项卡；保存后前台立即生效       |
| 插件       | 已装列表、启用、停用、卸载、上传安装                                       | 启用/停用立即生效，无需重启                              |
| 主题       | 已装列表、当前主题标识、切换                                               | 切换后前台立即换肤                                       |
| 应用市场   | 见第 13 节                                                                 | —                                                        |
| 开发者入驻 | 见第 14 节                                                                 | —                                                        |
| 伪静态     | 并入「设置 → 伪静态」，见第 16.12 节                                       | 重新检测与手动切档均可用                                 |
| 系统升级   | 当前版本、检查更新、手动升级、自动升级开关与通道、升级记录               | 升级后站点与数据正常，失败自动回滚                       |

通用要求：

- 所有写操作必须 POST + CSRF 校验
- 所有操作给出明确结果提示（成功 / 失败原因），不做「点了没反应」
- 表单校验失败时保留用户已填内容，不清空
- 后台页面不进入前台缓存

### 12.1 内置 Markdown 编辑器（三栏）

**内核只带一个编辑器：纯 Markdown 三栏。** 不做所见即所得，富文本编辑器以插件形式提供。

桌面宽度 ≥ 1024px 时横向三栏：

| 位置 | 内容                                                                 | 宽度  |
| ---- | -------------------------------------------------------------------- | ----- |
| 左   | Markdown 源码 `textarea`：等宽字体、行号、Tab 缩进 4 空格、`Ctrl+B/I/K` 快捷插入 | 1fr   |
| 中   | 渲染结果预览（服务端返回的 HTML，样式与前台正文一致）                | 1fr   |
| 右   | 文档目录：从 `#`~`######` 自动提取，缩进呈现层级，点击跳转，当前小节高亮 | 220px |

- 宽度 < 1024px 折叠为「编辑 / 预览 / 目录」三个 tab，默认编辑，不出现横向滚动
- 工具栏只用原生按钮：粗体、斜体、H2/H3、链接、图片、引用、代码块、有序/无序列表、插分隔线、预览刷新、字数统计
- 编辑器与预览**不引入任何第三方库**，`app/assets/editor.js` 未压缩体积 < 8KB，`admin.css` 中编辑器样式 < 4KB

**预览**

- 输入停止 300ms 后 POST `/admin/preview`（带 CSRF），返回渲染后的 HTML 片段
- 服务端用 `Markdown` 类渲染，与前台同一套代码，保证「预览即所得」
- 预览请求不写页面缓存，不触发前台 `the_content` 钩子

**滚动同步**

- 渲染时为每个顶层块写入 `data-src-line`（该块起始行号），标题块额外写 `id="h-{slug}"`
- 源码滚动 → 取视口顶部行号，找最近的 `data-src-line` 块，滚动预览使其对齐顶部
- 预览滚动 → 取最靠近顶部的块，把源码 `textarea` 滚到对应行
- 双向用 `requestAnimationFrame` 节流，并用 80ms 内的「程序触发滚动」标记避免互相回弹
- 预览尚未渲染完导致块级映射失败时，退化为按滚动百分比同步

**目录**

- 每次预览后重建，从源码 `^#{1,6}\s` 提取，层级缩进，点击滚动对应标题
- 当前可视区小节用 `IntersectionObserver` 高亮

### 12.2 内容格式双模式（内核内置）

「双模式」指内容格式双支持，由内核渲染层内置，与编辑器无关：

| 格式       | 写入方             | 存储                          | 渲染                                                       |
| ---------- | ------------------ | ----------------------------- | ---------------------------------------------------------- |
| `markdown` | 内置 Markdown 编辑器 | `post.content` 存 Markdown 原文 | 输出时 `Markdown::toHtml()`，结果按 `post.updated_at` 缓存   |
| `html`     | 富文本编辑器插件   | `post.content` 存 HTML 片段   | 输出前经 §17 的标签与属性白名单过滤                          |

- 两种格式共用 `content_format` 字段，前台模板不做判断，统一调用 `content_html($post)`
- 切换格式**不自动转换已有正文**，后台切换时提示「切换格式不会转换已有内容，请自行确认」
- 富文本插件通过 `admin_editor` 钩子替换编辑区，由插件自己产出 `content_format=html`；插件停用后，已存 html 的内容仍能正常渲染，但只能用内置编辑器改原文
- Markdown 解析只实现常用子集：标题、段落、粗斜体、行内代码、代码块、链接、图片、有序/无序列表、引用、表格、分隔线、自动链接；**不做完整 CommonMark**，不支持的语法按纯文本输出，不报错

---

## 13. 应用市场

### 13.1 范围（v1 只做这些）

1. 列表展示
2. 一键安装（下载 → 验签 → 解压 → 落库）
3. 启用 / 停用 / 卸载

**明确不做**：支付、分成、评分、评论、自动审核、版本灰度、依赖自动解析。

### 13.2 市场服务端契约

市场地址由 `config.php` 的 `market_url` 指定，默认同域 `/market/`。此目录要可以单独绑定域名和复制到别的服务器能用。删除该目录不能影响程序运行。

**接口一：清单**

```
GET {market_url}?a=items
```

返回 JSON：

```json
{
  "ok": true,
  "items": [
    {
      "type": "plugin",
      "slug": "sitemap-plus",
      "name": "增强站点地图",
      "version": "1.0.0",
      "author": "someone",
      "description": "为 sitemap 增加自定义 URL",
      "requires": "1.0.0",
      "download": "https://example.com/market/?a=download&slug=sitemap-plus",
      "checksum": "sha256 十六进制",
      "signature": "base64 编码的 RSA 签名",
      "size": 12345,
      "downloads": 128,
      "updated_at": 1763000000
    }
  ]
}
```

`type` 取值 `plugin` 或 `theme`。

**接口二：下载**

```
GET {market_url}?a=download&slug={slug}
```

返回 `application/zip` 二进制，文件名 `{slug}-{version}.zip`。

**接口三：开发者入驻**

```
POST {market_url}?a=developer_apply
字段：name, email, site, intro
```

返回 `{"ok":true}` 或 `{"ok":false,"msg":"原因"}`。

### 13.3 包格式

插件包（zip）结构：

```text
{slug}/
├── plugin.json
├── plugin.php
└── ... 其他文件
```

`plugin.json`：

```json
{
  "name": "增强站点地图",
  "slug": "sitemap-plus",
  "version": "1.0.0",
  "author": "someone",
  "description": "为 sitemap 增加自定义 URL",
  "requires": "1.0.0",
  "requires_hook_version": 1,
  "hooks": ["sitemap_urls"],
  "entry": "plugin.php"
}
```

主题包结构同理，根文件为 `theme.json`。

### 13.4 签名校验（必须实现）

- 市场方用私钥对「包文件的 SHA-256 摘要」做 RSA-SHA256 签名，签名值放清单的 `signature` 字段
- 客户端内置公钥（`app/Core/Market.php` 常量），下载后校验：
  1. 计算 zip 的 SHA-256，与清单 `checksum` 比对
  2. 用公钥验证 `signature` 对 `checksum` 的签名
  3. 两者任一不通过 → 拒绝安装，删除临时文件，提示「包校验失败」
- 校验失败不得跳过，不得提供「忽略校验」选项

### 13.5 安装流程

1. 下载 zip 到 `storage/cache/tmp/{随机}.zip`，限制最大 20MB，超时 30 秒
2. 校验 SHA-256 与签名
3. 解压到 `storage/cache/tmp/{随机}/`
4. 逐项检查解压结果：拒绝含 `..`、绝对路径、符号链接的条目
5. 校验包内 `plugin.json` / `theme.json` 存在且字段完整，`slug` 与清单一致
6. 移动目录到 `plugins/{slug}/` 或 `themes/{slug}/`；目标已存在则拒绝覆盖并提示
7. 写入 `plugin` 或 `theme` 表，`source=market`，记录 checksum
8. 清空钩子缓存与页面缓存
9. 返回成功，列表刷新后显示「已安装」

### 13.6 卸载规则

- 卸载插件：停用 → 删除目录 → 删除表记录 → 清缓存
- 卸载主题：当前启用的主题禁止卸载
- 卸载不删除插件自建的数据表（v1 无此机制，明确在文档写明）

---

## 14. 开发者入驻

### 14.1 站内表单

后台 `/admin/developer` 提供表单：名称、邮箱、个人站点、简介、插件包上传（可选）。

提交后：

1. 写入本地 `developer_app` 表，`status=pending`
2. 若配置了 `market_url`，同步 POST 到市场服务端 `?a=developer_apply`
3. 市场服务端写入 `market/data/developers.json`
4. 后台列表显示申请记录与状态

### 14.2 审核（人工）

- 市场服务端不做自动审核
- 站长在 `market/data/developers.json` 或后台列表中人工标记 `approved` / `rejected`
- 批准后由站长手动把插件包放入 `market/data/packages/` 并更新 `items.json`

**明确不做**：开发者后台、自动构建、自动测试、分成结算。

---

## 15. 安装器

### 15.1 入口与前置

- `index.php` 最前判断 `install.lock`：缺失时对 HTML 请求 **302** 跳转 `/install.php`；静态资源（`*.css|js|png|jpg|jpeg|gif|webp|ico|svg|woff2` 及 `/app/assets/`、`/themes/*/assets/`、`/storage/uploads/` 路径）与 `install.php` 自身直接放行，否则安装页样式会被自身重定向打掉，还可能形成死循环
- `install.php` 检测到 `install.lock` 已存在，直接输出「已安装」并拒绝执行
- 安装过程分 5 个步骤页：环境检测 → 数据库与站点信息 → 管理员账号 → 执行安装 → 完成

### 15.2 环境检测项

| 检测项          | 要求                       | 不通过时                  |
| --------------- | -------------------------- | ------------------------- |
| PHP 版本        | ≥ 8.1                      | 阻断，提示升级            |
| MySQL 扩展      | `pdo_mysql` 或 `mysqli` 之一存在 | 警告：默认模式不可用，引导改选 SQLite |
| SQLite 扩展     | `pdo_sqlite` 或 `sqlite3` 之一存在 | 警告：次选模式不可用；两组扩展都缺则阻断并提示联系主机商 |
| MySQL 连接      | 填写后实测连接成功，版本 ≥ 5.7 | 阻断，回填错误码与可读提示 |
| `mbstring` 扩展 | 存在                       | 阻断                      |
| `json` 扩展     | 存在                       | 阻断                      |
| `openssl` 扩展  | 存在                       | 阻断（插件与主题包签名校验必需） |
| `zip` 扩展      | 存在                       | 警告，市场安装功能不可用  |
| 目录可写        | `config/`、`storage/` 可写 | 阻断，给出具体 chmod 命令 |
| 上传限制        | `upload_max_filesize` ≥ 2M | 警告                      |
| HTTPS           | 建议开启                   | 警告                      |

阻断项任一不通过，不得进入下一步；警告项可继续但需在结果页复述。

### 15.3 数据目录选址

两种数据库都要选址，因为 `redirect.php` 与临时文件必须落在 web 根之外或受封锁的目录里。

1. 计算 `$parent = dirname(站点根目录)`
2. 尝试创建 `$parent/svel-data/`：
   - 成功且可写 → 使用该目录
   - 失败 → 进入第 3 步
3. 使用站内 `storage/data-{随机6位}/`，并写入 `.htaccess`（Apache 封锁）
4. 按数据库类型分别落配置：
   - **MySQL 模式**：不建数据库文件，选址只用于 `redirect.php` 与临时文件，落 `storage.dir`
   - **SQLite 模式**：在该目录创建 `svel.db`，落 `db.file`
5. 两种模式都把最终路径写进 `config.php` 的 `storage.dir`

### 15.4 三档 URL 探测

1. 生成 `probe-{随机8位}.php`，内容 `<?php echo 'SVEL_PROBE_OK';`
2. `GET /probe-xxx.php`，正文含 `SVEL_PROBE_OK` → PHP 可执行
3. `GET /__svel_rw_{随机}`（不存在的路径），返回 200 且正文含路由标记 → **L1**
4. 否则 `GET /index.php/__svel_pi_{随机}`，返回 200 → **L2**
5. 都不成立 → **L3**
6. 删除探测文件，结果写入 `config.php` 的 `url_mode`

必须走真实 HTTP 请求，禁止读取服务器配置文件猜测。

### 15.5 写规则文件

| 识别到的服务器       | 动作                                                                  |
| -------------------- | --------------------------------------------------------------------- |
| Apache / LiteSpeed   | 写 `.htaccess`，用 `# BEGIN SvelCMS` / `# END SvelCMS` 标记块插入     |
| IIS                  | 写 `web.config`，同样标记块合并                                       |
| Nginx / 宝塔 / Caddy | **不修改主配置**，把片段写入 `storage/rewrite/`，并在完成页与后台展示 |
| PHP 内置服务器       | 生成 `router.php`                                                     |
| 未知                 | 跳过，落 L3                                                           |

写文件前若目标已存在，必须先读原内容，仅追加或替换标记块区间，**不得整文件覆盖**。

### 15.6 建库与初始化

1. **建立连接**
   - MySQL：按表单填写的 host / port / database / username / password 连接，成功后执行 `SET NAMES utf8mb4`
   - SQLite：在数据目录创建 `svel.db`，连接后执行 `PRAGMA journal_mode=WAL`、`PRAGMA foreign_keys=ON`
2. **建库**
   - MySQL：目标库不存在且有 `CREATE` 权限则创建（`utf8mb4_unicode_ci`）；无权限则提示「请先在面板创建数据库并授权该账号」，不阻断下一步
   - SQLite：无此步骤
3. **建表**：按 7.10 映射生成 DDL，表名带前缀。MySQL 的 DDL 会隐式提交、**事务无法回滚建表**，因此失败时按「已建表清单逆序 DROP」清理，再报错；SQLite 用事务包裹，失败直接回滚
4. 写入 `option` 默认键值
5. 创建管理员：`password_hash` 生成，`role=admin`
6. 写入示例内容：1 个分类「默认分类」、1 篇文章「欢迎使用 SvelCMS」（`status=publish`，`content_format=markdown`，正文为一段 Markdown 示例）
7. 生成 `config.php`：按所选驱动写 `db` 分支（MySQL 写连接参数，SQLite 写 `file`）、`storage.dir`、站点地址、`base`、`url_mode`、`secret`（64 位随机）
8. 前端只依赖 `db.driver` 判断走哪条驱动，**业务代码里不出现任何方言判断**

### 15.7 自检

安装最后一步必须实测，全部通过才算成功：

1. 用当前档位请求 `/`，状态码 200 且正文含站点名
2. 请求示例文章地址，状态码 200 且正文含文章标题
3. 请求 `/sitemap.xml`，状态码 200 且为合法 XML
4. 请求一个不存在的地址，状态码 404

任一失败：回退一档 URL 模式后重试一次；仍失败则展示失败项与原始响应，不写 `install.lock`。

### 15.8 收尾

- 写 `install.lock`，内容为安装时间与版本号
- 完成页展示：站点地址、后台地址、管理员账号、当前 URL 档位、服务器类型、数据库类型与版本（MySQL x.x / SQLite x.x）、需手工处理的警告项
- 完成页提供「重新检测伪静态」与「复制伪静态规则」入口

---

## 16. 伪静态规则

### 16.1 原则

**规则是美化项，不是依赖项。** 任何环境、任何档位，不做任何伪静态配置也必须全站可访问，绝不出现 404 或白屏。删掉全部规则文件，站点只退化 URL 形态，不影响可用。

### 16.2 Apache 2.2 / 2.4（`.htaccess`，站点根）

```apache
# BEGIN SvelCMS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^ index.php [L]
</IfModule>
# END SvelCMS
```

### 16.3 Nginx（`server` 块内，放在 `location ~ \.php$` 之前）

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

### 16.4 宝塔 / 小皮面板（Nginx 内核）

面板 → 网站 → 设置 → 伪静态 → 选「自定义」，粘贴一行：

```
try_files $uri $uri/ /index.php?$args;
```

### 16.5 LiteSpeed / OpenLiteSpeed

直接使用 16.2 的 `.htaccess`。面板 rewrite 框使用无 `IfModule` 包裹版本：

```apache
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.php [L]
```

### 16.6 IIS 7+（`web.config`，站点根）

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="SvelCMS" stopProcessing="true">
          <match url="^(.*)$" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" appendQueryString="true" />
        </rule>
      </rules>
    </rewrite>
    <defaultDocument>
      <files>
        <clear />
        <add value="index.php" />
      </files>
    </defaultDocument>
  </system.webServer>
</configuration>
```

前提是已安装 URL Rewrite 模块；检测不到时自动落 L2/L3。

### 16.7 Caddy 2（`Caddyfile`）

```
example.com {
    root * /var/www/svelcms
    encode gzip
    try_files {path} /index.php?{query}
    php_fastcgi unix//run/php/php-fpm.sock
    file_server
}
```

`try_files` 必须写在 `php_fastcgi` 之前。

### 16.8 PHP 内置服务器（`router.php`）

```php
<?php
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}
require __DIR__ . '/index.php';
```

启动：`php -S 127.0.0.1:8000 router.php`

### 16.9 无重写权限的虚拟主机

不需要任何规则，自动走 L3，装完即用。

### 16.10 子目录部署变体（`https://site.com/blog/`）

| 环境   | 写法                                                                             |
| ------ | -------------------------------------------------------------------------------- |
| Apache | `RewriteBase /blog/`                                                             |
| Nginx  | `location /blog/ { try_files $uri $uri/ /blog/index.php?$args; }`                |
| IIS    | `<match url="^blog/(.*)$" />` + `<action type="Rewrite" url="blog/index.php" />` |
| Caddy  | `handle_path /blog/* { try_files {path} /blog/index.php?{query} ... }`           |

程序侧所有链接由 `url()` 自动拼接 base 前缀，不硬编码 `/`。

### 16.11 敏感目录封锁

Apache（`storage/` 内 `.htaccess`）：

```apache
Require all denied
```

Nginx：

```nginx
location ~ ^/(storage)/ { deny all; }
location ~* \.(db|db-wal|db-shm|log|bak|sql)$ { deny all; }
```

IIS（`web.config`）：

```xml
<security>
  <requestFiltering>
    <hiddenSegments>
      <add segment="storage" />
    </hiddenSegments>
    <fileExtensions>
      <add fileExtension=".db" allowed="false" />
    </fileExtensions>
  </requestFiltering>
</security>
```

### 16.12 伪静态设置（设置页选项卡）

伪静态不再单独占一个后台菜单，统一放在「设置 → 伪静态」；`POST /admin/setting/rewrite` 负责重新检测与手动切档。页面展示并提供：

1. 当前 URL 档位与判定原因
2. 站点子目录
3. 「重新检测」按钮，重新探测并写回配置
4. 手动切换档位（L1 / L2 / L3），用于误判兜底
5. Apache / Nginx / Caddy / IIS 四类规则片段，规则文件写入 `storage/rewrite/`

### 16.13 系统升级

**市场侧**：后台「CMS 版本」管理版本包，上传 zip 后从 `app/bootstrap.php` 自动识别 `SVEL_VERSION`，登记渠道（stable / beta）、更新说明、校验和与体积，可发布 / 下架 / 删除。对内核提供：

- `GET /api/cms/latest?channel=stable|beta`：stable 只返回稳定版，beta 返回两者中版本最高者
- `GET /download/cms/{version}`：返回版本包，并带 `X-Checksum-Sha256` 响应头

**站点侧**：后台「系统升级」提供当前版本、检查更新、立即升级、自动升级开关、升级通道与升级记录。

- 检查结果缓存 10 分钟，手动「检查更新」强制刷新，避免每次打开后台都等远端
- 升级前备份 `app/` 与入口文件到数据目录 `backups/cms-版本-时间.zip`
- 下载后校验 SHA-256 与体积，版本包地址必须位于 `market_url` 域内
- 只覆盖内核 `app/`、`index.php`、`router.php`，不动主题、插件、上传文件、`config.php` 与数据库
- 覆盖后先由旧代码补一次表结构，并写入 `schema_pending=1`，新代码在下次请求启动时再同步一次，保证新版本新增的表与字段生效
- 任何一步失败都保留旧内核；已替换的文件在异常时还原
- 自动升级在进入后台仪表盘时触发，距上次检查至少 6 小时，带文件锁防止并发；结果写入 `auto_upgrade_result` 并显示在升级页

---

## 17. 安全

| 项       | 要求                                                                                                          |
| -------- | ------------------------------------------------------------------------------------------------------------- |
| 密码     | `password_hash` / `password_verify`，禁止 md5/sha1                                                            |
| 会话     | 登录后 `session_regenerate_id(true)`；Cookie `HttpOnly` + `SameSite=Lax`                                      |
| CSRF     | 所有 POST 表单带令牌，服务端校验，失败返回 419 页                                                             |
| SQL      | 全部参数化绑定，`Db` 层禁止接收拼接后的 SQL                                                                   |
| XSS      | 所有输出经 `e()`；正文按格式处理：`html` 走白名单（标签 `p,a,img,h2-h4,ul,ol,li,blockquote,pre,code,strong,em,hr,table,thead,tbody,tr,th,td`；属性仅 `href,src,alt,title,class`；`href/src` 只允许 `http:`、`https:`、`/` 开头的相对地址，其它协议一律剥离；外链补 `rel="noopener"`；事件属性全删）；`markdown` 先渲染再走同一白名单 |
| 上传     | 扩展名白名单 `jpg,jpeg,png,gif,webp,ico,pdf,zip`；随机重命名；`uploads/` 目录写死禁执行（Apache `RemoveHandler`、Nginx 拒绝 `*.php`、IIS 同）；返回的 URL 由 `url()` 生成                                                       |
| 插件包   | SHA-256 + RSA 签名校验；解压拒绝 `..`、绝对路径、符号链接                                                     |
| 敏感路径 | 数据目录优先放 web 根之外；站内则配封锁规则                                                                   |
| 后台     | `/admin` 下所有路由强制登录；登录失败限流                                                                     |
| 错误输出 | `debug=false` 时不显示堆栈，只写 `storage/logs/`                                                              |

---

## 18. 性能指标与测法

| 指标         | 目标                 | 测法                                                 |
| ------------ | -------------------- | ---------------------------------------------------- |
| 首页执行时间 | MySQL < 60ms / SQLite < 30ms | 入口记 `microtime(true)`，输出页脚注释（debug 模式）；opcache 开、关各测一次 |
| 峰值内存     | < 8MB                | `memory_get_peak_usage(true)`                        |
| 首屏 HTML    | < 20KB               | 查看页面源码字节数                                   |
| CSS          | < 12KB               | `assets/style.css` 文件大小                          |
| 内核文件数   | < 200                | `find app themes/default -type f \| wc -l`           |
| 缓存命中     | < 15ms，直接输出，不进控制器 | 响应头 `X-Svel-Cache: HIT`，未命中为 `MISS`  |

实现要求：

- 页面缓存文件路径 `storage/cache/{md5(md5(url))}.html`，同时存一个 `.meta` 记录过期时间与状态码
- 缓存失效：发布、修改、删除文章时，删除对应 key 与首页、分类、标签、sitemap 的 key
- `option` 表在同一请求内只查一次，结果常驻内存
- 钩子映射从 `storage/cache/hooks.php` 读取，不查库

---

## 19. 编码规范

1. 每个 PHP 文件首行 `<?php`，第二行 `declare(strict_types=1);`，**不写结束标签**。例外：`themes/` 下的模板文件以 HTML 为输出主体，不写 `strict_types` 声明，只保证首行为 `<?php`
2. 文件头注释格式：

```php
/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Router.php
 * @desc 路由分发与三档 URL 归一化
 */
```

3. 所有类放在 `Svel\` 命名空间，autoload 按 `app/{相对路径}.php` 映射
4. 公开方法必须有返回类型声明；参数尽量标注类型
5. 异常统一抛 `RuntimeException` 或自定义 `SvelException`，入口统一捕获并转 500 页
6. 日志用 `error_log()` 写 `storage/logs/error-{Ymd}.log`，格式 `时间 | 级别 | 文件:行 | 消息`
7. 注释使用中文，解释「为什么」而非「做什么」
8. 禁止在循环内查询数据库（N+1），列表页一律批量查询后在 PHP 内组装

---

## 20. 实施阶段与验收

按顺序执行，**一次只做一个阶段**，每阶段验收通过后进入下一阶段。

### 阶段 1：工程骨架 + 安装器

任务：入口、autoload、配置、`Db`（MySQL / SQLite 双驱动 + 7.10 类型映射）、`Request`、`Response`、`Router`（三档归一化）、`View`、`install.php` 与 `Installer`（15 节九步）。

验收：

- [ ] 本地 `php -S` 能访问，未安装时跳转 `/install.php`
- [ ] 环境检测页正确显示每项结果，阻断项不可跳过
- [ ] MySQL 5.7 与 8.0 各安装一次成功，表前缀生效，字符集为 utf8mb4
- [ ] 同一份代码改用 SQLite 安装成功，前台表现与 MySQL 一致
- [ ] MySQL 建表中途故意失败后，已建的表被清理干净，不残留半套表
- [ ] 安装完成后生成 `config.php`、（SQLite 模式下的）数据库文件、`install.lock`
- [ ] 安装自检四项（首页、示例文章、sitemap、404）全部通过
- [ ] 二次访问 `/install.php` 被明确拒绝
- [ ] 删除 `install.lock` 后可重新安装

### 阶段 2：前台与 SEO 内核

任务：首页、文章、页面、分类、标签、搜索、分页、404、TDK、canonical、JSON-LD、sitemap、robots、RSS、页面缓存、`Markdown` 渲染（含 `content_format` 两种格式）、`Redirect` 301 查找。

验收：

- [ ] 首页、文章页、分类页、标签页、搜索页、404 页均返回正确状态码与内容
- [ ] 每页 title / description / canonical 正确且唯一
- [ ] 文章页输出 Article JSON-LD，首页输出 WebSite + Organization
- [ ] `/sitemap.xml` 为合法 XML，含 lastmod
- [ ] `/robots.txt` 含 sitemap 地址
- [ ] `/feed` 为合法 RSS 2.0
- [ ] Markdown 正文渲染为语义 HTML：标题、列表、代码块、表格、图片均正常，未支持的语法按纯文本输出不报错
- [ ] `content_format=html` 的正文经白名单过滤后输出，`<script>` 与事件属性被剥离
- [ ] 访问不存在的文章返回 404 状态码（非 200）
- [ ] 第二次访问同一页面命中缓存，响应头为 `X-Svel-Cache: HIT`

### 阶段 3：钩子机制与主题引擎

任务：`Hook`、`Plugin`、`Theme`、模板回退、钩子缓存、demo 插件。

验收：

- [ ] 未启用任何插件时，前台功能与阶段 2 完全一致
- [ ] 启用 demo 插件后，其声明的钩子生效
- [ ] demo 插件未声明的其他钩子被触发时，该插件文件不被加载（用 `file_exists` 断点或日志验证）
- [ ] 停用插件后立即失效，无需重启
- [ ] 主题模板缺失时自动回退到默认主题，不报错不白屏
- [ ] 插件启用时校验 `requires_hook_version`，不满足则拒绝并提示

### 阶段 4：后台管理

任务：登录、仪表盘、文章（含 Markdown 三栏编辑器）、分类、标签、设置、插件、主题管理、`/admin/preview` 与 `/admin/upload` 接口、slug 改名与 301 写入、`Redirect` 映射维护。

验收：

- [ ] 未登录访问 `/admin` 任意页面跳转登录页
- [ ] 登录成功后 `session` 更新，CSRF 令牌有效
- [ ] 连续 5 次错误密码后锁定
- [ ] 文章新增、编辑、删除、置顶、草稿发布全部可用，保存后前台立即可见
- [ ] **修改 slug 后旧地址返回 301 到新地址，新地址 200，旧路径缓存已失效**
- [ ] 连续改两次 slug（A→B→C）后访问 A 只跳一跳直达 C
- [ ] 改成已存在的 slug 或保留字 slug 被拒绝，原值保留并给出提示
- [ ] 删除分类后其文章归入未分类，文章未被删除
- [ ] 设置保存后前台立即生效
- [ ] **Markdown 三栏编辑器可用**：左源码、中预览、右目录；预览随输入更新；目录点击可定位；源码与预览滚动同步
- [ ] 编辑器插入的图片经 `/admin/upload` 上传后可正常显示
- [ ] 正文里的 `<script>` 标签与 `onerror` 一类属性被过滤
- [ ] 所有操作有明确成功或失败提示
- [ ] 所有写操作缺少 CSRF 令牌时被拒绝（419）

### 阶段 5：应用市场与开发者入驻

任务：`Market`、市场服务端、清单拉取、下载验签、安装、启用停用卸载、入驻表单。

验收：

- [ ] 后台市场页能拉取本地 `market/` 清单并展示
- [ ] 一键安装一个插件包成功，列表变为已安装
- [ ] 篡改包内容后安装被拒绝，提示校验失败
- [ ] 包内含 `../` 路径时解压被拒绝
- [ ] 启用、停用、卸载均生效并清理缓存
- [ ] 当前启用的主题不可卸载
- [ ] 入驻表单提交后本地表与市场端 `developers.json` 均落库
- [ ] 富文本编辑器插件安装启用后，编辑区被替换，保存的内容以 `content_format=html` 落库并在前台正常渲染
- [ ] 停用该插件后，已存 html 的正文仍能正常显示，编辑器回落到内置 Markdown 三栏

### 阶段 6：默认主题、联调与交付

任务：默认主题完整样式与移动端适配、整站联调、部署文档、打包。

验收：

- [ ] 默认主题在 375px 宽度下无横向滚动、导航可用
- [ ] 首屏 HTML < 20KB，CSS < 12KB
- [ ] 首页无缓存无插件状态：MySQL 模式 PHP < 60ms，SQLite 模式 < 30ms，峰值内存 < 8MB（均在开启 opcache 与未开启两种条件下各测一次，记录实测值）
- [ ] 内核文件数 < 200
- [ ] 安装验收清单（见下）全部通过
- [ ] 交付包解压后可直接运行 `install.php`

### 安装验收清单

- [ ] 全新 Apache 未做任何配置：装完首页与文章 200，URL 为 L1
- [ ] 默认 MySQL 模式（全新库）装完首页 200，后台可登录
- [ ] 次选 SQLite 模式装完首页 200，`svel.db` 位于 web 根之外或已被封锁
- [ ] 全新 Nginx 未加任何规则：装完不 404，自动落 L2 或 L3
- [ ] Nginx 加 `try_files` 后点「重新检测」：升到 L1，旧链接 301 到新地址
- [ ] Apache 关闭 `mod_rewrite`：安装不受影响，自动落 L2/L3
- [ ] 部署在 `/blog/` 子目录：前台链接、后台、sitemap、RSS 地址全部正确
- [ ] 站点根已有 `.htaccess`：安装后原内容完整保留，仅多出标记块
- [ ] 数据目录落在站内时：直接访问 `svel.db`（SQLite 模式）与 `redirect.php`（两种模式）均返回 403/404
- [ ] 二次访问 `install.php`：明确拒绝
- [ ] 卸载某插件后：站点仍正常，无报错

---

## 21. 交付清单

程序包内必须包含：

| 项         | 说明                                               |
| ---------- | -------------------------------------------------- |
| 全部源码   | 按第 5 节目录结构                                  |
| 默认主题   | 完整可用，移动端适配                               |
| 市场服务端 | `market/` 可独立部署，含示例清单                   |
| demo 插件  | `plugins/` 下不放，改为交付时附在 `docs/` 供验收用；含一个可选的富文本编辑器插件示例 |
| 编辑资源   | `app/assets/editor.js`、`app/assets/admin.css`，零第三方库 |
| 部署说明   | `docs/deploy.md`，含 MySQL / SQLite 两种建库步骤、各环境伪静态粘贴步骤 |
| 使用说明   | `docs/usage.md`，含后台各项操作、三栏编辑器快捷键、slug 改名与 301 说明 |
| 本文件     | 根目录 `SVELCMS开发说明.md`                        |

打包要求：单个 zip，解压后即为第 5 节根目录结构，不额外套一层目录。

---

## 22. 待确认项与默认值

遇到以下事项时按默认值执行，不要停下来询问：

| 事项               | 默认处理                                                          |
| ------------------ | ----------------------------------------------------------------- |
| 数据库             | **MySQL 5.7+ 为默认**，SQLite 3 为次选；两种驱动都实现、都验收。安装器默认选中 MySQL，扩展缺失或连接失败时引导改选 SQLite |
| 数据库连接         | MySQL 每请求新建连接，不做持久连接（共享主机常禁用 `p:host`）；页面缓存命中时不连数据库 |
| 内容格式           | 默认 `markdown`；`html` 仅由富文本插件写入；两格式共存，互不转换  |
| 编辑器             | 内核只带 Markdown 三栏编辑器，不做所见即所得；富文本编辑器一律走插件，不作内置 |
| slug 中文标题      | 不生成拼音，落 `post-{id}` / `cat-{id}` / `tag-{id}`，后台可手改；拼音化留给插件 |
| slug 修改与 301    | 允许修改；映射落数据目录 `redirect.php`，不建表、不入 option；链路压缩、防循环 |
| 其他面板环境       | 除第 16 节列出的 6 类外，一律按 L3 兜底，不新增规则               |
| 评论功能           | 不做。将来由插件实现                                              |
| 图片压缩 / 缩略图  | 不做自动处理，仅上传原图；`gd` 存在时可生成缩略图，缺失则跳过     |
| 多语言             | 不做。界面固定中文                                                |
| 文章分页条数默认值 | 10                                                                |
| 缓存有效期默认值   | 3600 秒                                                           |
| 后台界面风格       | 极简单色，无 UI 框架，单 CSS                                      |
| 升级 / 迁移        | 后台「系统升级」完成：只替换 `app/` 与入口文件，先备份后覆盖，失败回滚；主题、插件、上传与数据不受影响 |

---

## 附：一句话总结

**SvelCMS（苗条 CMS）——解压上传、跑一下 install.php，就得到一个默认零插件、零主题、快、且被搜索引擎强大且正常收录的博客站；要扩展，装插件。**