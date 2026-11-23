<?php

declare(strict_types=1);

namespace Alexw\Antibot;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Type\DateTime;

class DeviceTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'alexw_antibot_device';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new StringField('FP', ['size' => 64, 'required' => true]),
            new IntegerField('USER_ID', ['default_value' => 0]),
            new DatetimeField('FIRST_SEEN', ['required' => true, 'default_value' => static fn() => new DateTime()]),
            new DatetimeField('LAST_SEEN', ['required' => true, 'default_value' => static fn() => new DateTime()]),
            new IntegerField('HITS', ['default_value' => 0]),
            new TextField('DEVICE_INFO'),
            new StringField('IMAGE_PATH', ['size' => 255]),
        ];
    }
}
