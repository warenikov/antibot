<?php

declare(strict_types=1);

namespace Alexw\Antibot\Handler;

use Alexw\Antibot\Settings;
use Bitrix\Main\Loader;

class PageHandler
{
    public static function onPageStart(): void
    {
        if (!Loader::includeModule(Settings::MODULE_ID)) {
            return;
        }

        if (!Settings::isEnabled()) {
            return;
        }

        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }

        // Версия по mtime файла — сбрасывает агрессивный кеш nginx для статики.
        // В прод отдаём обфусцированную сборку, исходник лежит рядом как agent.js.
        $jsFile = 'agent.obf.js';
        $jsPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . Settings::MODULE_ID . '/' . $jsFile;
        if (!file_exists($jsPath)) {
            $jsFile = 'agent.js';
            $jsPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . Settings::MODULE_ID . '/' . $jsFile;
        }
        $version = file_exists($jsPath) ? filemtime($jsPath) : time();

        global $APPLICATION;
        $APPLICATION->AddHeadScript('/bitrix/js/' . Settings::MODULE_ID . '/' . $jsFile . '?v=' . $version);
    }
}
