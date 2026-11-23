<?php

declare(strict_types=1);

namespace Alexw\Antibot;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Type\DateTime;

class LogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'alexw_antibot_log';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new DatetimeField('DATE_X', ['required' => true, 'default_value' => static fn() => new DateTime()]),
            new StringField('IP', ['size' => 50]),
            new StringField('USER_AGENT', ['size' => 500]),
            new StringField('URI', ['size' => 500]),
            new IntegerField('SCORE', ['default_value' => 0]),
            new StringField('DECISION', ['size' => 10, 'required' => true]),
            new TextField('REASONS'),
            new TextField('PAYLOAD'),
            new StringField('DEVICE_FP', ['size' => 64]),
            new TextField('DEVICE_INFO'),
        ];
    }
}
