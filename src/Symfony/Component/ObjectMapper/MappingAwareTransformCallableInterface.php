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
 * A transformer receiving the #[Map] attribute being applied.
 *
 * The attribute is passed to __invoke() so that the transformer stays stateless and can be used as a service.
 * When a property is mapped, {@see Map::$source} and {@see Map::$target} are the names of the source and target properties, wherever the attribute is declared.
 * The object mapper always passes the attribute. It is null when other code calls the transformer as a TransformCallableInterface, with three arguments.
 *
 * @template T of object
 * @template T2 of object
 *
 * @extends TransformCallableInterface<T, T2>
 */
interface MappingAwareTransformCallableInterface extends TransformCallableInterface
{
    /**
     * @param mixed    $value   The value being mapped
     * @param T        $source  The object we're working on
     * @param T2|null  $target  The target we're mapping to
     * @param Map|null $mapping The mapping being applied
     */
    public function __invoke(mixed $value, object $source, ?object $target, ?Map $mapping = null): mixed;
}
