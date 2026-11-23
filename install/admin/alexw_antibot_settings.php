<?php

use Alexw\Antibot\Settings;
use Bitrix\Main\Config\Option;
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

$mid = 'alexw.antibot';

$fields = [
    'enabled'       => 'Y',
    'min_fill_ms'   => '1500',
    'captcha_score' => '50',
    'block_score'   => '80',
    'token_ttl'     => '3600',
    'ip_whitelist'  => '',
    'ip_blacklist'  => '',
    'save_fp_image' => 'N',
];

if (!empty($_POST['Update']) && check_bitrix_sessid()) {
    Option::set($mid, 'enabled', !empty($_POST['enabled']) ? 'Y' : 'N');
    Option::set($mid, 'min_fill_ms', (string)max(0, (int)$_POST['min_fill_ms']));
    Option::set($mid, 'captcha_score', (string)max(1, min(100, (int)$_POST['captcha_score'])));
    Option::set($mid, 'block_score', (string)max(1, min(100, (int)$_POST['block_score'])));
    Option::set($mid, 'token_ttl', (string)max(60, (int)$_POST['token_ttl']));
    Option::set($mid, 'ip_whitelist', mb_substr((string)$_POST['ip_whitelist'], 0, 4000));
    Option::set($mid, 'ip_blacklist', mb_substr((string)$_POST['ip_blacklist'], 0, 4000));
    Option::set($mid, 'save_fp_image', !empty($_POST['save_fp_image']) ? 'Y' : 'N');
    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&saved=Y');
}

$values = [];
foreach ($fields as $code => $default) {
    $values[$code] = Option::get($mid, $code, $default);
}

$APPLICATION->SetTitle('Антибот — настройки');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if (($_GET['saved'] ?? '') === 'Y') {
    CAdminMessage::ShowMessage(['MESSAGE' => 'Настройки сохранены', 'TYPE' => 'OK']);
}

$tabControl = new CAdminTabControl('antibotTabControl', [
    ['DIV' => 'main', 'TAB' => 'Защита форм', 'TITLE' => 'Пороги скоринга и токены'],
]);
?>

<form method="POST" action="<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>">
<?= bitrix_sessid_post() ?>
<input type="hidden" name="Update" value="Y">

<?php
$tabControl->Begin();
$tabControl->BeginNextTab();
?>

<tr>
    <td width="45%">Защита включена:</td>
    <td><input type="checkbox" name="enabled" value="Y"<?= $values['enabled'] === 'Y' ? ' checked' : '' ?>></td>
</tr>
<tr>
    <td>Минимальное время заполнения формы, мс:<br><small>Быстрее — признак бота (+30 к скору)</small></td>
    <td><input type="number" name="min_fill_ms" value="<?= (int)$values['min_fill_ms'] ?>" min="0" step="100" style="width:100px"></td>
</tr>
<tr>
    <td>Скор для капчи (0–100):</td>
    <td><input type="number" name="captcha_score" value="<?= (int)$values['captcha_score'] ?>" min="1" max="100" style="width:100px"></td>
</tr>
<tr>
    <td>Скор для блокировки (0–100):</td>
    <td><input type="number" name="block_score" value="<?= (int)$values['block_score'] ?>" min="1" max="100" style="width:100px"></td>
</tr>
<tr>
    <td>Время жизни токена, сек:</td>
    <td><input type="number" name="token_ttl" value="<?= (int)$values['token_ttl'] ?>" min="60" style="width:100px"></td>
</tr>
<tr>
    <td>Белый список IP:<br><small>по одному на строку; * в конце — префикс, напр. 192.168.*</small></td>
    <td><textarea name="ip_whitelist" rows="4" cols="45"><?= htmlspecialchars($values['ip_whitelist']) ?></textarea></td>
</tr>
<tr>
    <td>Чёрный список IP:</td>
    <td><textarea name="ip_blacklist" rows="4" cols="45"><?= htmlspecialchars($values['ip_blacklist']) ?></textarea></td>
</tr>
<tr>
    <td>Сохранять картинку canvas-фингерпринта:<br><small>PNG устройства в /upload/alexw.antibot/fp/, один раз на устройство</small></td>
    <td><input type="checkbox" name="save_fp_image" value="Y"<?= $values['save_fp_image'] === 'Y' ? ' checked' : '' ?>></td>
</tr>

<?php
$tabControl->EndTab();
$tabControl->Buttons(['btnSave' => true, 'btnApply' => false, 'btnCancel' => false]);
$tabControl->End();
?>

</form>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
