<?php

declare(strict_types=1);

namespace Alexw\Antibot;

class TokenService
{
    public static function issue(string $deviceFp = ''): string
    {
        $payload = [
            'iat' => time(),
            'jti' => bin2hex(random_bytes(8)),
        ];
        if ($deviceFp !== '') {
            $payload['fp'] = $deviceFp;
        }
        $body = self::b64Encode((string)json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $body . '.' . self::b64Encode(self::sign($body));
    }

    public static function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $sig] = $parts;

        $expected = self::b64Encode(self::sign($body));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = json_decode(self::b64Decode($body), true);
        if (!is_array($payload) || empty($payload['iat'])) {
            return null;
        }

        $now = time();
        if ($payload['iat'] > $now + 60) {
            return null;
        }
        if ($now - $payload['iat'] > Settings::tokenTtl()) {
            return null;
        }

        return $payload;
    }

    private static function secret(): string
    {
        $secret = \COption::GetOptionString(Settings::MODULE_ID, 'secret_key', '');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            \COption::SetOptionString(Settings::MODULE_ID, 'secret_key', $secret);
        }
        return $secret;
    }

    private static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::secret(), true);
    }

    private static function b64Encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function b64Decode(string $value): string
    {
        return (string)base64_decode(strtr($value, '-_', '+/'), true);
    }
}
