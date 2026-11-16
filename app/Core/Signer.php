<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Signer.php
 * @desc 发布包签名校验：Ed25519 公钥验签，防止市场或传输链路被替换版本包
 */

namespace Svel\Core;

final class Signer
{
    /** 内置发布公钥；可通过 config.php 的 release_public_key 覆盖，私钥绝不进入仓库 */
    public const PUBLIC_KEY = 'B4crDVffna/O4FE9pgc2iwivJfEC0Frqzm3Vl6Eyl8s=';

    public static function verifyFile(string $file, string $signature): bool
    {
        if ($signature === '' || !is_file($file) || !function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        $sig = base64_decode($signature, true);
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        $publicKey = (string)Config::get('release_public_key', self::PUBLIC_KEY);
        $pub = base64_decode($publicKey, true);
        if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        $digest = hash_file('sha256', $file, true);
        if ($digest === false) {
            return false;
        }
        return sodium_crypto_sign_verify_detached($sig, $digest, $pub);
    }
}