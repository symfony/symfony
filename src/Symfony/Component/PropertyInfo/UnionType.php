<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Union type value object (immutable).
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class UnionType
{
    private $types;

    /**
     * @param Type[] $types
     */
    public function __construct(array $types)
    {
        foreach ($types as $type) {
            if (!$type instanceof Type) {
                throw new \TypeError(sprintf('"%s()": Argument #1 ($types) must be an array with items of type "%s", "%s" given.', __METHOD__, Type::class, get_debug_type($type)));
            }
        }

        $this->types = $types;
    }

    /**
     * @return Type[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}
