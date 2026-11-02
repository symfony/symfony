<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class DummyIntersectionType
{
    /**
     * @var \Traversable&\Countable
     */
    public $objects;

    /**
     * @var array{limit?: int, offset?: int}&array{name?: string}
     */
    public $arrayShapes;

    /**
     * @var ?(\Traversable&\Countable)
     */
    public $nullable;

    /**
     * @var (\Traversable&\Countable)|null
     */
    public $orNull;

    /**
     * @var (\Traversable&\Countable)|string
     */
    public $orString;

    /**
     * @var array<\Traversable&\Countable>
     */
    public $collection;
}
