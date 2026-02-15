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

/**
 * @template T of object
 */
abstract class ParentDummyWithTemplate
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
