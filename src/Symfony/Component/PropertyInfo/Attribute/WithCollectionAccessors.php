<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Attribute;

use Symfony\Component\PropertyInfo\Exception\LogicException;

/**
 * Declares the element mutators of a collection class. When a property is
 * typed with a collection, PropertyAccess writes into the existing instance by
 * calling these methods, instead of expecting add*()/remove*() mutators on the
 * owning object.
 *
 * Doctrine collections provide add() and removeElement() out of the box, so
 * this attribute is only needed for collections with custom names.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class WithCollectionAccessors
{
    public function __construct(
        public readonly string $adder,
        public readonly string $remover,
    ) {
        if (!$adder) {
            throw new LogicException('The "adder" argument must not be empty.');
        }

        if (!$remover) {
            throw new LogicException('The "remover" argument must not be empty.');
        }
    }
}
