<?php

namespace App\Enums;

enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function label(): string
    {
        return $this->name;
    }

    /** @return array<int, array{value: int, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $day) => ['value' => $day->value, 'label' => $day->label()],
            self::cases(),
        );
    }
}
