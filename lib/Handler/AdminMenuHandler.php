<?php

declare(strict_types=1);

namespace Alexw\Antibot\Handler;

use Alexw\Antibot\Settings;
use Bitrix\Main\Loader;

class AdminMenuHandler
{
    public static function onBuildGlobalMenu(array &$globalMenu, array &$moduleMenu): void
    {
        if (!Loader::includeModule(Settings::MODULE_ID)) {
            return;
        }

        global $APPLICATION, $USER;

        if (!$USER->IsAdmin() && $APPLICATION->GetGroupRight(Settings::MODULE_ID) < 'R') {
            return;
        }

        $moduleMenu[] = [
            'parent_menu' => 'global_menu_services',
            'section'     => 'alexw_antibot',
            'sort'        => 500,
            'url'         => 'alexw_antibot_settings.php',
            'text'        => 'Антибот',
            'title'       => 'Защита форм от ботов',
            'icon'        => 'fileman_menu_icon',
            'page_icon'   => 'fileman_page_icon',
            'items_id'    => 'menu_alexw_antibot',
            'items'       => [
                [
                    'text'  => 'Настройки',
                    'url'   => 'alexw_antibot_settings.php?lang=' . LANGUAGE_ID,
                    'title' => 'Пороги, токены, списки IP',
                ],
                [
                    'text'  => 'Журнал проверок',
                    'url'   => 'alexw_antibot_log.php?lang=' . LANGUAGE_ID,
                    'title' => 'Скоринг и решения по формам',
                ],
            ],
        ];
    }
}
