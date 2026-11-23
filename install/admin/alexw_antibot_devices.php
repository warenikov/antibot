<?php

use Alexw\Antibot\DeviceTable;
use Bitrix\Main\Loader;

define('ADMIN_MODULE_NAME', 'alexw.antibot');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

if (!Loader::includeModule('alexw.antibot')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    die();
}

if (!$USER->IsAdmin() && $APPLICATION->GetGroupRight('alexw.antibot') < 'X') {
    $APPLICATION->AuthForm('Нет доступа');
}

$APPLICATION->SetTitle('Антибот — устройства');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$sTableID = 'alexw_antibot_devices';
$oSort = new CAdminSorting($sTableID, 'LAST_SEEN', 'DESC');
$lAdmin = new CAdminList($sTableID, $oSort);

$rsData = DeviceTable::getList([
    'order' => [$by => $order],
    'limit' => 100,
]);

$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint('Устройства'));

$lAdmin->AddHeaders([
    ['id' => 'ID',         'content' => 'ID',          'sort' => 'ID',        'default' => true],
    ['id' => 'FP',         'content' => 'Паспорт (fp)',                     'default' => true],
    ['id' => 'USER_ID',    'content' => 'Пользователь', 'sort' => 'USER_ID',  'default' => true],
    ['id' => 'HITS',       'content' => 'Хиты',         'sort' => 'HITS',     'default' => true],
    ['id' => 'DEVICE',     'content' => 'Железо',                           'default' => true],
    ['id' => 'IMAGE',      'content' => 'Картинка',                         'default' => true],
    ['id' => 'LAST_SEEN',  'content' => 'Последний',    'sort' => 'LAST_SEEN','default' => true],
    ['id' => 'FIRST_SEEN', 'content' => 'Первый',       'sort' => 'FIRST_SEEN','default' => false],
]);

$userCache = [];

while ($row = $rsData->Fetch()) {
    $listRow = $lAdmin->AddRow($row['ID'], $row);

    $listRow->AddViewField('FP', '<span style="font-size:10px" title="' . htmlspecialchars((string)$row['FP']) . '">'
        . htmlspecialchars(substr((string)$row['FP'], 0, 16)) . '…</span>');

    $userHtml = '—';
    $uid = (int)$row['USER_ID'];
    if ($uid > 0) {
        if (!array_key_exists($uid, $userCache)) {
            $rsUser = CUser::GetByID($uid);
            $userCache[$uid] = $rsUser->Fetch() ?: null;
        }
        $u = $userCache[$uid];
        $userHtml = $u
            ? '[' . $uid . '] ' . htmlspecialchars(trim(($u['NAME'] ?? '') . ' ' . ($u['LAST_NAME'] ?? '')) . ' (' . ($u['LOGIN'] ?? '') . ')')
            : '[' . $uid . ']';
    }
    $listRow->AddViewField('USER_ID', $userHtml);

    $deviceHtml = '';
    if (!empty($row['DEVICE_INFO'])) {
        $device = json_decode((string)$row['DEVICE_INFO'], true);
        if (is_array($device)) {
            $parts = [];
            if (!empty($device['gpuRenderer'])) {
                $parts[] = '<b>' . htmlspecialchars((string)$device['gpuRenderer']) . '</b>';
            }
            if (!empty($device['screen'])) {
                $parts[] = htmlspecialchars((string)$device['screen']);
            }
            if (!empty($device['cores'])) {
                $parts[] = (int)$device['cores'] . ' ядер';
            }
            if (!empty($device['timezone'])) {
                $parts[] = htmlspecialchars((string)$device['timezone']);
            }
            $deviceHtml = implode('<br>', $parts);
        }
    }
    $listRow->AddViewField('DEVICE', $deviceHtml !== '' ? $deviceHtml : '—');

    $imageHtml = '—';
    if (!empty($row['IMAGE_PATH'])) {
        $path = htmlspecialchars((string)$row['IMAGE_PATH']);
        $imageHtml = '<a href="' . $path . '" target="_blank"><img src="' . $path . '" style="max-width:180px;border:1px solid #ddd"></a>';
    }
    $listRow->AddViewField('IMAGE', $imageHtml);

    $listRow->AddViewField('LAST_SEEN', (string)$row['LAST_SEEN']);
    $listRow->AddViewField('FIRST_SEEN', (string)$row['FIRST_SEEN']);
}

$lAdmin->DisplayList();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
