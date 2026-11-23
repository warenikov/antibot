<?php

declare(strict_types=1);

namespace Alexw\Antibot;

class Score
{
    public const DECISION_PASS    = 'pass';
    public const DECISION_CAPTCHA = 'captcha';
    public const DECISION_BLOCK   = 'block';

    private const FLAG_WEIGHTS = [
        'webdriver'         => 60,
        'webdriverPatched'  => 40,
        'domAutomation'     => 60,
        'phantom'           => 50,
        'nodejs'            => 30,
        'headlessUA'        => 40,
        'noLanguagesPlugins' => 15,
        'swiftshaderGPU'    => 50,
        'fpMismatch'        => 40,
    ];

    /**
     * @param array $flags     бот-флаги из JS-агента
     * @param array $metrics   поведенческие метрики: durationMs, mouseMoves, mouseDistance, keyPresses, clicks
     * @param string $honeypot значение honeypot-поля (у человека пустое)
     * @return array{score: int, decision: string, reasons: string[]}
     */
    public static function evaluate(array $flags, array $metrics, string $honeypot): array
    {
        $score   = 0;
        $reasons = [];

        if ($honeypot !== '') {
            return ['score' => 100, 'decision' => self::DECISION_BLOCK, 'reasons' => ['honeypot']];
        }

        foreach (self::FLAG_WEIGHTS as $flag => $weight) {
            if (!empty($flags[$flag])) {
                $score += $weight;
                $reasons[] = 'flag:' . $flag;
            }
        }

        $durationMs    = max(0, (int)($metrics['durationMs'] ?? 0));
        $mouseMoves    = max(0, (int)($metrics['mouseMoves'] ?? 0));
        $mouseDistance = max(0, (int)($metrics['mouseDistance'] ?? 0));
        $keyPresses    = max(0, (int)($metrics['keyPresses'] ?? 0));
        $clicks        = max(0, (int)($metrics['clicks'] ?? 0));

        if ($durationMs > 0 && $durationMs < Settings::minFillMs()) {
            $score += 30;
            $reasons[] = 'behavior:too_fast';
        }

        if ($durationMs >= Settings::minFillMs() && $mouseMoves === 0 && $keyPresses === 0) {
            $score += 25;
            $reasons[] = 'behavior:no_activity';
        }

        if ($mouseMoves > 0 && $mouseDistance > 0 && $mouseDistance / max(1, $mouseMoves) > 500) {
            $score += 10;
            $reasons[] = 'behavior:teleporting_mouse';
        }

        if ($clicks === 0 && $durationMs > 0) {
            $score += 5;
            $reasons[] = 'behavior:no_clicks';
        }

        $score = min(100, $score);

        if ($score >= Settings::blockScore()) {
            $decision = self::DECISION_BLOCK;
        } elseif ($score >= Settings::captchaScore()) {
            $decision = self::DECISION_CAPTCHA;
        } else {
            $decision = self::DECISION_PASS;
        }

        return ['score' => $score, 'decision' => $decision, 'reasons' => $reasons];
    }
}
