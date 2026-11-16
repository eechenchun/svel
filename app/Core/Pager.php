<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Pager.php
 * @desc 分页数据：当前页附近留窗口，其余折叠，避免长列表把页面撑大
 */

namespace Svel\Core;

final class Pager
{
    /**
     * @param callable(int):string $urlOf 传入页码返回地址
     * @return array{page:int,pages:int,prev:?string,next:?string,items:array<int,array{n:int|string,url:string,current:bool}>}
     */
    public static function build(int $page, int $pages, callable $urlOf): array
    {
        $page = max(1, min($page, max(1, $pages)));
        $items = [];
        $window = 1;

        for ($i = 1; $i <= $pages; $i++) {
            $near = abs($i - $page) <= $window || $i === 1 || $i === $pages;
            if (!$near) {
                if (end($items) !== ['n' => '…', 'url' => '', 'current' => false]) {
                    $items[] = ['n' => '…', 'url' => '', 'current' => false];
                }
                continue;
            }
            $items[] = ['n' => $i, 'url' => (string)$urlOf($i), 'current' => $i === $page];
        }

        return [
            'page'  => $page,
            'pages' => $pages,
            'prev'  => $page > 1 ? (string)$urlOf($page - 1) : null,
            'next'  => $page < $pages ? (string)$urlOf($page + 1) : null,
            'items' => $items,
        ];
    }
}