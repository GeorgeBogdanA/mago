<?php

declare(strict_types=1);

namespace App\Entity;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
}
