<?php

declare(strict_types=1);

namespace Alexw\Antibot\Controller;

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
}
