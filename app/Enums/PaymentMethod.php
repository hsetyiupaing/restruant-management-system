<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'CASH';
    case Card = 'CARD';
    case Online = 'ONLINE';
}
