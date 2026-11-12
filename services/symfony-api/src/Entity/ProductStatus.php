<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Only Published products are public (spec products-list, AC2).
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
