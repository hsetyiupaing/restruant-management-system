<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Generates human-readable, collision-resistant order numbers.
 *
 * Format: ORD-YYYYMMDD-XXXXXXXX using a Crockford-style alphabet (no I, L, O, U)
 * so numbers survive being read aloud or typed by hand. Eight random characters
 * give ~1.1e12 combinations per day; the orders.order_number unique index is the
 * final backstop and callers retry on a duplicate-key error.
 */
class OrderNumberGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(): string
    {
        $suffix = '';

        for ($i = 0; $i < 8; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'ORD-'.now()->format('Ymd').'-'.Str::upper($suffix);
    }
}
