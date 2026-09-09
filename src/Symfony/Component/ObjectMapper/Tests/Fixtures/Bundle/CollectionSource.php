<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\Bundle;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

#[Map(target: CollectionTarget::class)]
final class CollectionSource
{
    /**
     * @param CollectionSourceItem[] $items
     */
    public function __construct(
        #[Map(transform: MapCollection::class)]
        public array $items,
    ) {
    }
}
