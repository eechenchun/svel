<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/User.php
 * @desc 用户：v1 只有管理员一种角色
 */

namespace Svel\Models;

use Svel\Core\Db;

final class User
{
    public static function findById(int $id): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('user')) . ' WHERE ' . $db->qi('id') . ' = ? LIMIT 1', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('user')) . ' WHERE ' . $db->qi('username') . ' = ? LIMIT 1', [$username]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Db::inst()->update('user', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], Db::inst()->qi('id') . ' = ?', [$id]);
    }

    public static function updateProfile(int $id, array $data): void
    {
        Db::inst()->update('user', $data, Db::inst()->qi('id') . ' = ?', [$id]);
    }
}