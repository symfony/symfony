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
 * Service used by "Map::transform".
 *
 * @template T of object
 *
 * @experimental
 *
 * {@see Symfony\Component\ObjectMapper\Attribute\Map}
 */
interface TransformCallableInterface
{
    /**
     * @param mixed $value  The value being mapped
     * @param T     $object The object we're working on
     */
    public function __invoke(mixed $value, object $object): mixed;
}
