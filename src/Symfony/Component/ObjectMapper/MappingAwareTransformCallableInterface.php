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

use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * Service used by "Map::transform".
 *
 * @template T of object
 * @template T2 of object
 *
 * {@see Symfony\Component\ObjectMapper\Attribute\Map}
 */
interface MappingAwareTransformCallableInterface
{
    /**
     * @param mixed   $value  The value being mapped
     * @param T       $source The object we're working on
     * @param T2|null $target The target we're mapping to
     * @param Map     $map    The original Map attribute
     */
    public function __invoke(mixed $value, object $source, ?object $target, Map $map): mixed;
}
