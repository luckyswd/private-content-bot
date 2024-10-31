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
        return match($type) {
            self::CHARGERS => 'Зарядки',
            self::TRAINING_HOME_WITHOUT_EQUIPMENT => 'Программа тренировок для дома без инвентаря/ с гантелями',
            self::TRAINING_HOME_WITH_ELASTIC => 'Программа тренировок для дома с резинками',
            self::TRAINING_FOR_GYM => 'Программа тренировок для тренировки для зала',
        };
    }
}
