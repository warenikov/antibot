<?php

declare(strict_types=1);

namespace Alexw\Antibot;

/**
 * Настройки модуля.
 *
 * Используется COption (старый API): класс доступен на самых ранних этапах
 * bootstrap ядра, включая событие OnPageStart. D7-класс Option на этом этапе
 * ещё не подключён и вызывает фатал.
 */
class Settings
{
    public const MODULE_ID = 'alexw.antibot';

    public static function isEnabled(): bool
    {
        return \COption::GetOptionString(self::MODULE_ID, 'enabled', 'Y') === 'Y';
    }

    public static function tokenTtl(): int
    {
        return max(60, \COption::GetOptionInt(self::MODULE_ID, 'token_ttl', 3600));
    }

    public static function minFillMs(): int
    {
        return max(0, \COption::GetOptionInt(self::MODULE_ID, 'min_fill_ms', 1500));
    }

    public static function captchaScore(): int
    {
        return max(1, \COption::GetOptionInt(self::MODULE_ID, 'captcha_score', 50));
    }

    public static function blockScore(): int
    {
        return max(1, \COption::GetOptionInt(self::MODULE_ID, 'block_score', 80));
    }

    /** @return string[] */
    public static function ipWhitelist(): array
    {
        return self::ipList('ip_whitelist');
    }

    /** @return string[] */
    public static function ipBlacklist(): array
    {
        return self::ipList('ip_blacklist');
    }

    public static function ipMatches(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($entry === '') {
                continue;
            }
            if (str_ends_with($entry, '*')) {
                if (str_starts_with($ip, rtrim($entry, '*'))) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] */
    private static function ipList(string $code): array
    {
        $raw = \COption::GetOptionString(self::MODULE_ID, $code, '');
        $rows = preg_split('/[\r\n,;]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $rows)));
    }
}
