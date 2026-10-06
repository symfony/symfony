<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Tests\Fixtures\Catalog\Product as CatalogProduct;
use Symfony\Component\JsonSchema\Tests\Fixtures\Inventory\Product as InventoryProduct;

class ProductPair
{
    public CatalogProduct $catalog;
    public InventoryProduct $inventory;
}
