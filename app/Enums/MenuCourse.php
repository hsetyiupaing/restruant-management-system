<?php

namespace App\Enums;

enum MenuCourse: string
{
    case Starter = 'STARTER';
    case Main = 'MAIN';
    case Dessert = 'DESSERT';

    /**
     * The course as it is shown on the storefront.
     */
    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Main => 'Main',
            self::Dessert => 'Dessert',
        };
    }
}
