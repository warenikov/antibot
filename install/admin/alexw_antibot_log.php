<?php

use Alexw\Antibot\LogTable;
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

$APPLICATION->SetTitle('Антибот — журнал проверок');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$sTableID = 'alexw_antibot_log';
$oSort = new CAdminSorting($sTableID, 'ID', 'DESC');
$lAdmin = new CAdminList($sTableID, $oSort);

$rsData = LogTable::getList([
    'order'  => [$by => $order],
    'limit'  => 50,
    'select' => ['ID', 'DATE_X', 'IP', 'USER_AGENT', 'URI', 'SCORE', 'DECISION', 'REASONS', 'DEVICE_FP', 'DEVICE_INFO'],
]);

$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint('Записи'));

$headers = [
    ['id' => 'ID',         'content' => 'ID',       'sort' => 'ID',     'default' => true],
    ['id' => 'DATE_X',     'content' => 'Дата',     'sort' => 'DATE_X', 'default' => true],
    ['id' => 'IP',         'content' => 'IP',       'sort' => 'IP',     'default' => true],
    ['id' => 'SCORE',      'content' => 'Скор',     'sort' => 'SCORE',  'default' => true],
    ['id' => 'DECISION',   'content' => 'Решение',                        'default' => true],
    ['id' => 'REASONS',    'content' => 'Причины',                        'default' => true],
    ['id' => 'DEVICE',     'content' => 'Устройство',                     'default' => true],
    ['id' => 'URI',        'content' => 'URL',                            'default' => false],
    ['id' => 'USER_AGENT', 'content' => 'User-Agent',                     'default' => false],
];
$lAdmin->AddHeaders($headers);

$colors = ['pass' => '#e8f5e9', 'captcha' => '#fff8e1', 'block' => '#ffebee'];

while ($row = $rsData->Fetch()) {
    $listRow = $lAdmin->AddRow($row['ID'], $row);
    $listRow->AddViewField('DATE_X', (string)$row['DATE_X']);
    $listRow->AddViewField('IP', htmlspecialchars((string)$row['IP']));
    $decision = (string)$row['DECISION'];
    $style = isset($colors[$decision]) ? ' style="background:' . $colors[$decision] . ';padding:2px 8px;border-radius:3px"' : '';
    $listRow->AddViewField('SCORE', '<b>' . (int)$row['SCORE'] . '</b>');
    $listRow->AddViewField('DECISION', '<span' . $style . '>' . htmlspecialchars($decision) . '</span>');
    $listRow->AddViewField('REASONS', htmlspecialchars((string)$row['REASONS']));

    $deviceHtml = '—';
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
            if (!empty($device['platform'])) {
                $parts[] = htmlspecialchars((string)$device['platform']);
            }
            $deviceHtml = implode('<br>', $parts);
        }
    }
    if (!empty($row['DEVICE_FP'])) {
        $deviceHtml .= '<br><span style="font-size:10px;color:#999" title="' . htmlspecialchars((string)$row['DEVICE_FP']) . '">fp: '
            . htmlspecialchars(substr((string)$row['DEVICE_FP'], 0, 12)) . '…</span>';
    }
    $listRow->AddViewField('DEVICE', $deviceHtml);
    $listRow->AddViewField('URI', htmlspecialchars((string)$row['URI']));
    $listRow->AddViewField('USER_AGENT', '<span style="font-size:11px">' . htmlspecialchars((string)$row['USER_AGENT']) . '</span>');
}

$lAdmin->DisplayList();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
