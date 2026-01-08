<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Attribute;

/**
 * Configures a how to map each item of an array.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class MapCollection extends Map
{
    /**
     * @param class-string|null                                                                                        $itemClass The class to map each collection item to
     * @param string|class-string|null                                                                                 $source    The property or the class to map from
     * @param string|class-string|null                                                                                 $target    The property or the class to map to
     * @param string|bool|callable(mixed, object): bool|null                                                           $if        A boolean, a service id or a callable that instructs whether to map
     * @param (string|callable(mixed, object, ?object): mixed)|(string|callable(mixed, object, ?object): mixed)[]|null $transform A service id or a callable that transforms the value during mapping
     */
    public function __construct(
        public readonly ?string $itemClass = null,
        ?string $target = null,
        ?string $source = null,
        mixed $if = null,
        mixed $transform = \Symfony\Component\ObjectMapper\Transform\MapCollection::class,
    ) {
        parent::__construct($target, $source, $if, $transform);
    }
}
