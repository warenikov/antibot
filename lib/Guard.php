<?php

declare(strict_types=1);

namespace Alexw\Antibot;

use Bitrix\Main\Application;

class Guard
{
    /**
     * Полная проверка отправки формы: токен + поведенческий скоринг + лог.
     *
     * @return array{ok: bool, decision: string, score: int, reasons: string[], error?: string}
     */
    public static function check(string $token, string $payloadJson): array
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $ip      = (string)$request->getRemoteAddress();
        $ua      = substr((string)$request->getUserAgent(), 0, 500);
        $uri     = substr((string)$request->getRequestUri(), 0, 500);

        if (!Settings::isEnabled()) {
            return ['ok' => true, 'decision' => Score::DECISION_PASS, 'score' => 0, 'reasons' => ['disabled']];
        }

        if (Settings::ipMatches($ip, Settings::ipBlacklist())) {
            $result = ['ok' => false, 'decision' => Score::DECISION_BLOCK, 'score' => 100, 'reasons' => ['ip_blacklist']];
            self::log($ip, $ua, $uri, $result, $payloadJson);
            return $result;
        }

        if (Settings::ipMatches($ip, Settings::ipWhitelist())) {
            return ['ok' => true, 'decision' => Score::DECISION_PASS, 'score' => 0, 'reasons' => ['ip_whitelist']];
        }

        $tokenPayload = TokenService::validate($token);
        if ($tokenPayload === null) {
            $result = ['ok' => false, 'decision' => Score::DECISION_BLOCK, 'score' => 100, 'reasons' => ['token_invalid'], 'error' => 'token_invalid'];
            self::log($ip, $ua, $uri, $result, $payloadJson);
            return $result;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            $result = ['ok' => false, 'decision' => Score::DECISION_BLOCK, 'score' => 100, 'reasons' => ['payload_invalid'], 'error' => 'payload_invalid'];
            self::log($ip, $ua, $uri, $result, $payloadJson);
            return $result;
        }

        $flags    = is_array($payload['flags'] ?? null) ? $payload['flags'] : [];
        $metrics  = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        $honeypot = (string)($payload['hp'] ?? '');
        $deviceFp = (string)($payload['fp'] ?? '');
        $device   = is_array($payload['device'] ?? null) ? $payload['device'] : [];

        if (!empty($tokenPayload['fp']) && $deviceFp !== '' && !hash_equals($tokenPayload['fp'], $deviceFp)) {
            $flags['fpMismatch'] = true;
        }

        $verdict = Score::evaluate($flags, $metrics, $honeypot);

        $result = [
            'ok'       => $verdict['decision'] !== Score::DECISION_BLOCK,
            'decision' => $verdict['decision'],
            'score'    => $verdict['score'],
            'reasons'  => $verdict['reasons'],
        ];

        self::log($ip, $ua, $uri, $result, $payloadJson, $deviceFp, $device);

        return $result;
    }

    private static function log(string $ip, string $ua, string $uri, array $result, string $payloadJson, string $deviceFp = '', array $device = []): void
    {
        LogTable::add([
            'DATE_X'      => new \Bitrix\Main\Type\DateTime(),
            'IP'          => $ip,
            'USER_AGENT'  => $ua,
            'URI'         => $uri,
            'SCORE'       => $result['score'],
            'DECISION'    => $result['decision'],
            'REASONS'     => implode(', ', $result['reasons']),
            'PAYLOAD'     => substr($payloadJson, 0, 65000),
            'DEVICE_FP'   => $deviceFp !== '' ? substr($deviceFp, 0, 64) : null,
            'DEVICE_INFO' => $device !== [] ? json_encode($device, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
