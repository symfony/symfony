<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

class ArrayOfUnionTypeContainerDummy
{
    /** @param array<UnionTypeFooDummy|UnionTypeBarDummy> $items */
    public function __construct(
        public readonly array $items = [],
    ) {
    }
}
