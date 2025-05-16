<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Attribute;

use Attribute;

/**
 * Maps an array of associative data to an array of typed objects.
 *
 * Example:
 *     #[MapCollection(of: ProductDTO::class)]
 *     public array $products;
 *
 * @experimental
 *
 * @author Devoton <oton.traore@email.com>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapCollection
{
    /**
     * @param class-string $of The class to map each element to.
     */
    public function __construct(public string $of) {}
}
