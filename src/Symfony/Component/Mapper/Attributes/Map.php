<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Attributes;

/**
 * Configures a class or a property to map to.
 *
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 *
 * @psalm-type CallableType = string|callable(mixed $value, object $object): mixed
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Map
{
    /**
     * @param string|class-string|null                                      $to        The property or the class to map to
     * @param string|bool|callable(mixed $value, object $object): bool|null $if        A boolean, Symfony service name or a callable that instructs whether to map
     * @param CallableType|CallableType[]|null                              $transform A Symfony service name or a callable that transform the value during mapping
     */
    public function __construct(public readonly ?string $to = null, public readonly mixed $if = null, public readonly mixed $transform = null)
    {
    }
}
