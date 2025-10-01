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
 * Configures a class or a property to map to.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Map
{
    /**
     * @param string|class-string|null                                                               $source    The property or the class to map from
     * @param string|class-string|null                                                               $target    The property or the class to map to
     * @param string|bool|callable(mixed, object): bool|null                                         $if        A boolean, a service id or a callable that instructs whether to map
     * @param (string|callable(mixed, object): mixed)|(string|callable(mixed, object): mixed)[]|null $transform A service id or a callable that transforms the value during mapping
     */
    public function __construct(
        public readonly ?string $target = null,
        public readonly ?string $source = null,
        public readonly mixed $if = null,
        public readonly mixed $transform = null,
    ) {
    }
}
