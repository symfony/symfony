<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

/**
 * @experimental
 *
 * An interface representing that gets called by "Map::if" and "Map::transform".
 *
 * {@see Symfony\Component\ObjectMapper\Attribute\Map}
 */
interface CallableInterface
{
    /**
     * @param mixed $value  the value being mapped
     * @param mixed $object the object we're working on
     */
    public function __invoke(mixed $value, object $object): mixed;
}
