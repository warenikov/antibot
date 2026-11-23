<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class alexw_antibot extends CModule
{
    public $MODULE_ID           = 'alexw.antibot';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME        = Loc::getMessage('ALEXW_ANTIBOT_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ALEXW_ANTIBOT_MODULE_DESCRIPTION');
        $this->PARTNER_NAME       = Loc::getMessage('ALEXW_ANTIBOT_PARTNER_NAME');
        $this->PARTNER_URI        = Loc::getMessage('ALEXW_ANTIBOT_PARTNER_URI');
    }

    public function DoInstall(): bool
    {
        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallTasks();
        $this->InstallEvents();
        $this->installOptions();
        return true;
    }

    public function DoUninstall(): bool
    {
        Loader::includeModule($this->MODULE_ID);
        $this->UnInstallEvents();
        $this->UnInstallTasks();
        $this->UnInstallFiles();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return true;
    }

    public function InstallFiles(): void
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
        CopyDirFiles(__DIR__ . '/js', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID, true, true);
    }

    public function UnInstallFiles(): void
    {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_antibot_settings.php');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/alexw_antibot_log.php');
        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID . '/');
    }

    public function InstallDB(): void
    {
        Application::getConnection()->queryExecute("
            CREATE TABLE IF NOT EXISTS alexw_antibot_log (
                ID INT NOT NULL AUTO_INCREMENT,
                DATE_X DATETIME NOT NULL,
                IP VARCHAR(50) NULL,
                USER_AGENT VARCHAR(500) NULL,
                URI VARCHAR(500) NULL,
                SCORE INT NOT NULL DEFAULT 0,
                DECISION VARCHAR(10) NOT NULL,
                REASONS TEXT NULL,
                PAYLOAD MEDIUMTEXT NULL,
                DEVICE_FP VARCHAR(64) NULL,
                DEVICE_INFO TEXT NULL,
                PRIMARY KEY (ID),
                KEY ix_datex (DATE_X),
                KEY ix_ip (IP)
            )
        ");
    }

    public function UnInstallDB(): void
    {
        Application::getConnection()->queryExecute("DROP TABLE IF EXISTS alexw_antibot_log");
    }

    public function InstallTasks(): void
    {
        global $DB;

        $DB->Query(
            "INSERT IGNORE INTO b_task (NAME, MODULE_ID, LETTER, BINDING, DESCRIPTION)
             VALUES ('alexw_antibot_admin', 'alexw.antibot', 'X', 'module', 'Управление модулем антибот-защиты')"
        );

        $rs = $DB->Query("SELECT ID FROM b_task WHERE MODULE_ID = 'alexw.antibot' AND LETTER = 'X'");
        if ($row = $rs->Fetch()) {
            $taskId = (int)$row['ID'];
            $DB->Query("INSERT IGNORE INTO b_group_task (GROUP_ID, TASK_ID) VALUES (1, {$taskId})");
        }
    }

    public function UnInstallTasks(): void
    {
        global $DB;
        $DB->Query("DELETE FROM b_task WHERE MODULE_ID = 'alexw.antibot'");
    }

    public function InstallEvents(): void
    {
        $em = EventManager::getInstance();
        $em->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\\Alexw\\Antibot\\Handler\\PageHandler', 'onPageStart');
        $em->registerEventHandler('main', 'OnBuildGlobalMenu', $this->MODULE_ID, '\\Alexw\\Antibot\\Handler\\AdminMenuHandler', 'onBuildGlobalMenu');
    }

    public function UnInstallEvents(): void
    {
        $em = EventManager::getInstance();
        $em->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID, '\\Alexw\\Antibot\\Handler\\PageHandler', 'onPageStart');
        $em->unRegisterEventHandler('main', 'OnBuildGlobalMenu', $this->MODULE_ID, '\\Alexw\\Antibot\\Handler\\AdminMenuHandler', 'onBuildGlobalMenu');
    }

    private function installOptions(): void
    {
        if (\COption::GetOptionString($this->MODULE_ID, 'secret_key', '') === '') {
            \COption::SetOptionString($this->MODULE_ID, 'secret_key', bin2hex(random_bytes(32)));
        }
    }
}
