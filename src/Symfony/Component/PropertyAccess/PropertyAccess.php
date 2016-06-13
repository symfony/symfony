<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Entry point of the PropertyAccess component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class PropertyAccess
{
    /**
     * Creates a property accessor.
     *
     * For dealing with several property accessor configured differently, use
     * the createPropertyAccessorBuilder() method instead.
     *
     * @param bool                        $throwExceptionOnInvalidIndex
     * @param bool                        $magicCall
     * @param CacheItemPoolInterface|null $cacheItemPool
     *
     * @return PropertyAccessor The new property accessor
     */
    public static function createPropertyAccessor($magicCall = false, $throwExceptionOnInvalidIndex = false, CacheItemPoolInterface $cacheItemPool = null)
    {
        return new PropertyAccessor($magicCall, $throwExceptionOnInvalidIndex, $cacheItemPool);
    }

    /**
     * Creates a property accessor builder.
     *
     * @return PropertyAccessorBuilder The new property accessor builder
     */
    public static function createPropertyAccessorBuilder()
    {
        return new PropertyAccessorBuilder();
    }

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}
