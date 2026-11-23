<?php

namespace App\Enum;

enum CalendarView: string
{
    case Day = 'day';
    case Week = 'week';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Week => 'Week',
        };
    }

    public function shortcut(): string
    {
        return match ($this) {
            self::Day => 'D',
            self::Week => 'W',
        };
    }
}
