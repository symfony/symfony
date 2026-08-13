<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\RecursionCacheMultiTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

#[Map(target: ItemTarget::class)]
class ItemSource
{
    public int $id = 1;

    /** @var ChildSource[] */
    #[Map(transform: new MapCollection(targetClass: ChildTarget::class))]
    public array $children = [];
}
