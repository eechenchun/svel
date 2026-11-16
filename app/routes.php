<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/routes.php
 * @desc 路由表。控制器统一签名：方法名(Request $req, array $params)
 */

return [
    // 前台
    ['GET', '/', 'HomeController@index'],
    ['GET', '/page/{n}', 'HomeController@page'],
    ['GET', '/post/{slug}.html', 'PostController@show'],
    ['GET', '/category/{slug}', 'CategoryController@show'],
    ['GET', '/category/{slug}/page/{n}', 'CategoryController@page'],
    ['GET', '/tag/{slug}', 'TagController@show'],
    ['GET', '/tag/{slug}/page/{n}', 'TagController@page'],
    ['GET', '/search', 'SearchController@index'],
    ['GET', '/feed', 'FeedController@index'],
    ['GET', '/sitemap.xml', 'SitemapController@index'],
    ['GET', '/sitemap-pages.xml', 'SitemapController@pages'],
    ['GET', '/sitemap-taxonomy.xml', 'SitemapController@taxonomy'],
    ['GET', '/sitemap-{n}.xml', 'SitemapController@posts'],
    ['GET', '/robots.txt', 'RobotsController@index'],
    ['GET', '/{slug}.html', 'PageController@show'],

    // 后台：登录
    ['GET', '/admin/login', 'Admin\\AuthController@form'],
    ['POST', '/admin/login', 'Admin\\AuthController@login'],
['GET', '/admin/logout', 'Admin\\AuthController@logout'],
    ['GET', '/admin/2fa', 'Admin\\TwoFactorController@challenge'],
    ['POST', '/admin/2fa', 'Admin\\TwoFactorController@challenge'],
    ['GET', '/admin/2fa/setup', 'Admin\\TwoFactorController@setup'],
    ['POST', '/admin/2fa/setup', 'Admin\\TwoFactorController@setup'],

    // 后台：仪表盘
    ['GET', '/admin', 'Admin\\DashboardController@index'],

    // 后台：文章
    ['GET', '/admin/post', 'Admin\\PostController@index'],
    ['GET', '/admin/post/edit', 'Admin\\PostController@form'],
    ['POST', '/admin/post/edit', 'Admin\\PostController@save'],
    ['POST', '/admin/post/delete', 'Admin\\PostController@delete'],
    ['POST', '/admin/post/toggle', 'Admin\\PostController@toggle'],

    // 后台：分类与标签
    ['GET', '/admin/category', 'Admin\\CategoryController@index'],
    ['POST', '/admin/category/save', 'Admin\\CategoryController@save'],
    ['POST', '/admin/category/delete', 'Admin\\CategoryController@delete'],
    ['GET', '/admin/tag', 'Admin\\TagController@index'],
    ['POST', '/admin/tag/save', 'Admin\\TagController@save'],
    ['POST', '/admin/tag/delete', 'Admin\\TagController@delete'],
    // 后台：导航
    ['GET', '/admin/nav', 'Admin\\NavController@index'],
    ['POST', '/admin/nav/save', 'Admin\\NavController@save'],
    ['POST', '/admin/nav/delete', 'Admin\\NavController@delete'],


    // 后台：设置
    ['GET', '/admin/setting', 'Admin\\SettingController@index'],
    ['POST', '/admin/setting', 'Admin\\SettingController@save'],

    // 后台：插件与主题
    ['GET', '/admin/plugin', 'Admin\\PluginController@index'],
    ['POST', '/admin/plugin/toggle', 'Admin\\PluginController@toggle'],
    ['POST', '/admin/plugin/upload', 'Admin\\PluginController@upload'],
    ['POST', '/admin/plugin/scan', 'Admin\PluginController@scan'],
    ['GET', '/admin/theme', 'Admin\\ThemeController@index'],
    ['POST', '/admin/theme/activate', 'Admin\\ThemeController@activate'],
    ['GET', '/admin/market', 'Admin\MarketController@index'],
    ['POST', '/admin/market/install', 'Admin\MarketController@install'],
    ['POST', '/admin/market/toggle', 'Admin\MarketController@toggle'],
    ['POST', '/admin/market/uninstall', 'Admin\MarketController@uninstall'],
    ['POST', '/admin/market/check', 'Admin\MarketController@check'],
    ['POST', '/admin/market/update', 'Admin\MarketController@update'],
    ['POST', '/admin/market/login', 'Admin\MarketController@loginMarket'],
    ['POST', '/admin/market/unbind', 'Admin\MarketController@unbind'],
    ['GET', '/admin/market/settings', 'Admin\MarketController@settings'],
    ['GET', '/admin/audit', 'Admin\\AuditController@index'],
    ['POST', '/admin/maintenance', 'Admin\\MaintenanceController@run'],
    ['GET', '/admin/upgrade', 'Admin\\UpgradeController@index'],
    ['POST', '/admin/upgrade/check', 'Admin\\UpgradeController@check'],
    ['POST', '/admin/upgrade/run', 'Admin\\UpgradeController@upgrade'],
    ['POST', '/admin/upgrade/toggle', 'Admin\\UpgradeController@toggle'],
    ['POST', '/admin/market/settings', 'Admin\MarketController@settings'],

    // 后台：编辑器接口与上传
    ['POST', '/admin/preview', 'Admin\\PreviewController@render'],
    ['POST', '/admin/upload', 'Admin\\UploadController@upload'],
    ['POST', '/admin/upload/insert', 'Admin\\UploadController@upload'],

// 后台：伪静态（并入设置页的“伪静态”选项卡）
    ['POST', '/admin/setting/rewrite', 'Admin\\SettingController@rewrite'],
];