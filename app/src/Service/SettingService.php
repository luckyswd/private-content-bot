<?php

namespace App\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;

class SettingService
{
    public function __construct(
        private SettingRepository $settingRepository,
    )
    {}

    public function getParameterValue(string $name): ?string {
        $parameter = $this->settingRepository->findOneBy(['name' => $name]);

        return $parameter instanceof Setting ? $parameter->getValue() : null;
    }
}