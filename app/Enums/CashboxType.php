<?php

namespace App\Enums;

enum CashboxType: string
{
    case Main = 'main';
    case Side = 'side';

    public function label(): string
    {
        return $this === self::Main ? 'الصندوق الرئيسي' : 'الصندوق الجانبي';
    }
}
