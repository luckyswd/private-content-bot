<?php

namespace App\Enum;

enum SubscriptionType: int
{
    case CHARGERS = 1;
    case TRAINING_HOME_WITHOUT_EQUIPMENT = 2;
    case TRAINING_HOME_WITH_ELASTIC = 3;
    case TRAINING_FOR_GYM = 4;

    public static function getRUname(SubscriptionType $type): string
    {
        return match ($type) {
            self::CHARGERS => 'Зарядки',
            self::TRAINING_HOME_WITHOUT_EQUIPMENT => 'Для дома без инвентаря или с гантелями',
            self::TRAINING_HOME_WITH_ELASTIC => 'Для дома с резинками',
            self::TRAINING_FOR_GYM => 'Для тренировки для зала',
        };
    }

    public static function getRUnameByStringType(string $stringType): string
    {
        $type = match ($stringType) {
            self::CHARGERS->name => self::CHARGERS,
            self::TRAINING_HOME_WITHOUT_EQUIPMENT->name => self::TRAINING_HOME_WITHOUT_EQUIPMENT,
            self::TRAINING_HOME_WITH_ELASTIC->name => self::TRAINING_HOME_WITH_ELASTIC,
            self::TRAINING_FOR_GYM->name => self::TRAINING_FOR_GYM,
        };

        return self::getRUname($type);
    }
}
