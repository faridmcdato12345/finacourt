<?php

namespace App\Support;

final class Money
{
    public static function cents(string|int|null $amount): int
    {
        $value = trim((string) $amount);

        if (preg_match('/^(-)?(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid monetary amount [{$value}].");
        }

        $cents = ((int) $matches[2] * 100)
            + (int) str_pad($matches[3] ?? '0', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
