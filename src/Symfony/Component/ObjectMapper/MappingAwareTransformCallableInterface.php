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
 * The attribute is passed to __invoke() so that the transformer stays stateless and can be used
 * as a service. It is nullable only because an interface cannot add a required parameter to an
 * inherited signature: the mapper always passes it. {@see Map::$target} is null when the mapping
 * is declared on the target side with #[Map(source: ...)].
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
