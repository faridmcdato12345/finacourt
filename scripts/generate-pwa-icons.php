<?php

declare(strict_types=1);

/** @return array{int, int, int, int} */
function pixel(int $x, int $y, int $size, bool $maskable): array
{
    $green = [20, 109, 74, 255];
    $white = [255, 255, 255, 255];
    $transparent = [0, 0, 0, 0];
    $radius = (int) round($size * 0.22);

    if (! $maskable) {
        $inside = ($x >= $radius && $x < $size - $radius)
            || ($y >= $radius && $y < $size - $radius);

        if (! $inside) {
            $cx = $x < $radius ? $radius : $size - $radius - 1;
            $cy = $y < $radius ? $radius : $size - $radius - 1;
            $inside = (($x - $cx) ** 2 + ($y - $cy) ** 2) <= $radius ** 2;
        }

        if (! $inside) {
            return $transparent;
        }
    }

    $left = (int) round($size * 0.18);
    $right = (int) round($size * 0.82);
    $top = (int) round($size * 0.25);
    $bottom = (int) round($size * 0.75);
    $line = max(3, (int) round($size * 0.04));
    $half = intdiv($line, 2);
    $insideCourt = $x >= $left - $half && $x <= $right + $half
        && $y >= $top - $half && $y <= $bottom + $half;
    $onBorder = $insideCourt && (
        abs($x - $left) <= $half || abs($x - $right) <= $half
        || abs($y - $top) <= $half || abs($y - $bottom) <= $half
    );
    $onCenter = $insideCourt && (
        abs($x - intdiv($size, 2)) <= $half
        || abs($y - intdiv($size, 2)) <= $half
    );

    return ($onBorder || $onCenter) ? $white : $green;
}

function chunk(string $type, string $data): string
{
    $payload = $type.$data;

    return pack('N', strlen($data)).$payload.pack('N', crc32($payload));
}

function writePng(string $path, int $size, bool $maskable = false): void
{
    $raw = '';

    for ($y = 0; $y < $size; $y++) {
        $raw .= "\x00";

        for ($x = 0; $x < $size; $x++) {
            $raw .= pack('C4', ...pixel($x, $y, $size, $maskable));
        }
    }

    $png = "\x89PNG\r\n\x1a\n"
        .chunk('IHDR', pack('NNC5', $size, $size, 8, 6, 0, 0, 0))
        .chunk('IDAT', gzcompress($raw, 9))
        .chunk('IEND', '');

    file_put_contents($path, $png);
}

$directory = dirname(__DIR__).'/public/icons';
writePng("{$directory}/court-marketplace-192.png", 192);
writePng("{$directory}/court-marketplace-512.png", 512);
writePng("{$directory}/court-marketplace-maskable-512.png", 512, true);
