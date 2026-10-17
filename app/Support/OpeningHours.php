<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Reads the restaurants.opening_hours JSON column.
 *
 * Format: {"mon": {"open": "09:00", "close": "22:00"}, ...} keyed mon–sun.
 * A null value means the restaurant sets no restrictions.
 */
class OpeningHours
{
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @param  array<string, array{open?: string, close?: string}>|null  $hours
     */
    public function __construct(private readonly ?array $hours) {}

    public function applies(CarbonInterface $moment): bool
    {
        if ($this->hours === null) {
            return true;
        }

        $window = $this->hours[self::DAY_KEYS[$moment->dayOfWeekIso - 1]] ?? null;

        if ($window === null || ! isset($window['open'], $window['close'])) {
            return false;
        }

        $open = $moment->copy()->setTimeFromTimeString($window['open']);
        $close = $moment->copy()->setTimeFromTimeString($window['close']);

        // A close time of or before the open time means the window ends at midnight.
        if ($close->lessThanOrEqualTo($open) || $window['close'] === '00:00') {
            $close = $close->addDay();
        }

        return $moment->betweenIncluded($open, $close);
    }

    public function isAlwaysOpen(): bool
    {
        return $this->hours === null;
    }
}
