<?php

declare(strict_types=1);

namespace Alexw\Antibot\Controller;

use Alexw\Antibot\DeviceTracker;
use Alexw\Antibot\Guard;
use Alexw\Antibot\TokenService;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;

class Api extends Controller
{
    public function configureActions(): array
    {
        return [
            'issue' => ['prefilters' => []],
            'check' => ['prefilters' => []],
            'track' => ['prefilters' => []],
        ];
    }

    public function issueAction(string $fp = ''): array
    {
        return [
            'token' => TokenService::issue($fp),
            'iat'   => time(),
        ];
    }

    public function checkAction(string $token = '', string $payload = ''): ?array
    {
        if ($token === '') {
            $this->addError(new Error('Token is required', 'token_required'));
            return null;
        }

        return Guard::check($token, $payload);
    }

    public function trackAction(string $fp = '', string $device = '', string $image = ''): array
    {
        $deviceInfo = [];
        if ($device !== '') {
            $decoded = json_decode($device, true);
            if (is_array($decoded)) {
                $deviceInfo = $decoded;
            }
        }

        return DeviceTracker::track($fp, $deviceInfo, $image);
    }
}
