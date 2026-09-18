<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestCollectionWithCustomMutatorsOwner
{
    private TestCollectionWithCustomMutators $items;

    public function __construct()
    {
        $this->items = new TestCollectionWithCustomMutators();
    }

    public function getItems(): TestCollectionWithCustomMutators
    {
        return $this->items;
    }
}
