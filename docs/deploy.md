# 部署说明

## 1. 环境

| 项 | 要求 | 备注 |
| --- | --- | --- |
| PHP | ≥ 8.1 | 代码按 8.1 语法编写，8.4 上实测通过 |
| MySQL 扩展 | `pdo_mysql` 或 `mysqli` | 默认数据库，缺一个就选 SQLite |
| SQLite 扩展 | `pdo_sqlite` 或 `sqlite3` | 次选数据库 |
| 其他扩展 | `mbstring`、`json`、`openssl`、`zip` | 缺少 `openssl` 会阻断安装（插件包校验依赖） |
| 目录权限 | 根目录可写（生成 `config.php`、`install.lock`）；`storage/` 可写 | Linux 下建议 755 / 属主为 PHP 运行用户 |

## 2. 数据库

### MySQL（默认）

1. 在主机面板创建数据库，字符集选 `utf8mb4`。
2. 记下库名、账号、密码、地址与端口。
3. 安装时选择 MySQL，填上述信息。安装器会自动建表（`svel_` 前缀），不需要手动导入 SQL。
4. 只建表不建库：若账号没有 `CREATE DATABASE` 权限，先在面板建库，安装器会跳过建库直接建表。

要求：MySQL 5.7 以上、InnoDB、`utf8mb4_unicode_ci`。表结构里字符串主键与唯一索引长度按 191 设计，兼容 5.7 的索引前缀限制。

### SQLite（次选）

1. 安装时选择 SQLite，安装器会在数据目录生成 `svel.db`。
2. 数据目录优先放在 **web 根之外** 的 `svel-data/`；放不进去时回落到 `storage/data-随机6位/`，并写入封锁规则。
3. 程序连接时启用 WAL 与 `foreign_keys=ON`，删除文章会级联清理标签关联。

注意：部分共享主机未编译 `pdo_sqlite`，安装页会直接提示，此时只能选 MySQL。

## 3. 伪静态

安装器会探测当前服务器能力，自动选中一档：

| 档位 | 形态 | 适用 |
| --- | --- | --- |
| L1 | `/post/hello.html` | 重写规则生效，推荐 |
| L2 | `/index.php/post/hello.html` | 无重写但有 PATH_INFO |
| L3 | `/index.php?p=post/hello.html` | 两者都不可用 |

### Apache

根目录 `.htaccess` 已随包提供，安装器会按子目录自动改写 `RewriteBase`。要求 `mod_rewrite` 开启、`AllowOverride` 至少为 `FileInfo`。

### Nginx

把下面内容放进站点的 `server { }`，且位于 `location ~ \.php$` **之前**：

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}

location ~ ^/storage/(cache|logs|rewrite)/ {
    deny all;
}

location ~* \.(db|sqlite)$ {
    deny all;
}
```

若站点面板已经 `include` 了根目录的 `nginx.htaccess`（phpstudy 默认如此），直接把 `try_files` 等指令写进该文件即可，无需改 vhost。改完执行 `nginx -t` 校验，再重载。

### 子目录部署

站点在 `https://example.com/blog/` 时：

- 安装页的「站点地址」填到子目录，结尾不要斜杠；
- Nginx 的 `try_files` 目标改为 `/blog/index.php?$args`；
- Apache 的 `RewriteBase` 由安装器自动写成 `/blog/`。

## 4. 安全

- 安装完成后删除或改名 `install.php`；程序检测到 `install.lock` 后会拒绝二次安装，但文件本身仍建议移除。
- `storage/` 下的 `cache`、`logs`、`rewrite`、`data-*` 不允许外部访问：Apache 用 `.htaccess`，Nginx 用上面的 `deny` 段。
- `storage/uploads/` 必须禁止执行 PHP：包内已带 `.htaccess`；Nginx 需自行加 `location ~* /storage/uploads/.*\.php$ { deny all; }`。
- `config.php` 含密钥，不要提交到版本库。
- 后台登录连续失败 5 次会锁该 IP 10 分钟，计数文件在数据目录 `cache/login-*.json`。

## 5. 升级与迁移

- 升级：覆盖 `app/`、`themes/default/` 与根目录入口文件，保留 `config.php`、`storage/`、`plugins/` 与数据库。
- 迁移：导出数据库 + 打包 `storage/uploads/` + `config.php`，到新机后改 `config.php` 的连接信息即可。
- 切数据库类型（MySQL ↔ SQLite）属于重建站：需要重新安装并导入内容。

## 6. 排错

| 现象 | 先看这里 |
| --- | --- |
| 白屏或 500 | 数据目录 `logs/error-*.log` 最后几行；后台仪表盘直接显示日志路径 |
| 文章页 404，首页正常 | URL 档位与重写规则不匹配，去后台「伪静态」重新检测 |
| 后台 419 | 表单令牌过期，刷新页面重提；多为页面停留过久 |
| 图片上传失败 | `storage/uploads` 权限、PHP `upload_max_filesize`、`post_max_size` |
| 首页看不到新文章 | 页面缓存未失效；后台保存一次即可清空，或在设置里把缓存秒数改小 |

## 7. 与规格的差异

- 应用市场与开发者入驻推迟到 1.1，本版不包含 `market/`。
- 编辑器改为两个插件：Markdown 三栏（默认启用）、富文本（默认停用）；都不启用时是纯文本框。
- 内核内置「内容格式双模式」：正文按 `markdown` / `html` 两种格式存储与渲染，富文本插件产出 HTML。
- 首页标题为「站点名 - 副标题」，副标题为空时只显示站点名。