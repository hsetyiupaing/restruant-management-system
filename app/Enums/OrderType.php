<?php

namespace App\Enums;

enum OrderType: string
{
    case Pickup = 'PICKUP';
    case Delivery = 'DELIVERY';
}
