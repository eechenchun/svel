<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/TwoFactor.php
 * @desc TOTP 两步验证：RFC 6238，30 秒步长，兼容 Google Authenticator
 */

namespace Svel\Core;

final class TwoFactor
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function enabled(array $user): bool
    {
        return (int)($user['totp_enabled'] ?? 0) === 1 && (string)($user['totp_secret'] ?? '') !== '';
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '';
        }
        $counter = (int)floor(($time ?? time()) / self::PERIOD);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer = 'SvelCMS'): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer) . '&period=' . self::PERIOD . '&digits=' . self::DIGITS;
    }

    /** @return array{plain:array<int,string>,hash:array<int,string>} */
    public static function recoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hash = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $plain[] = $code;
            $hash[] = password_hash($code, PASSWORD_DEFAULT);
        }
        return ['plain' => $plain, 'hash' => $hash];
    }

    public static function verifyRecovery(string $hashesJson, string $code): ?string
    {
        $hashes = json_decode($hashesJson, true);
        if (!is_array($hashes)) {
            return null;
        }
        $code = strtoupper(trim($code));
        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($hashes[$index]);
                return json_encode(array_values($hashes)) ?: '[]';
            }
        }
        return null;
    }

    private static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                return '';
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}