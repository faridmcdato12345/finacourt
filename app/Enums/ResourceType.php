<?php

namespace App\Enums;

enum ResourceType: string
{
    case Court = 'court';
    case Field = 'field';
    case Studio = 'studio';
    case Lane = 'lane';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
