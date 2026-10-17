<?php

namespace App\Enums;

enum StaffType: string
{
    case Cashier = 'CASHIER';
    case Kitchen = 'KITCHEN';
    case Waiter = 'WAITER';
    case Driver = 'DRIVER';
}
