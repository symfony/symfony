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

/**
 * Entry point of the PropertyAccess component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.2.0
 */
final class PropertyAccess
{
    /**
     * Creates a property accessor with the default configuration.
     *
     * @return PropertyAccessor The new property accessor
     *
     * @since v2.3.0
     */
    public static function createPropertyAccessor()
    {
        return self::createPropertyAccessorBuilder()->getPropertyAccessor();
    }

    /**
     * Creates a property accessor builder.
     *
     * @return PropertyAccessorBuilder The new property accessor builder
     *
     * @since v2.3.0
     */
    public static function createPropertyAccessorBuilder()
    {
        return new PropertyAccessorBuilder();
    }

    /**
     * Alias of {@link getPropertyAccessor}.
     *
     * @return PropertyAccessor The new property accessor
     *
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link createPropertyAccessor()} instead.
     *
     * @since v2.3.0
     */
    public static function getPropertyAccessor()
    {
        return self::createPropertyAccessor();
    }

    /**
     * This class cannot be instantiated.
     *
     * @since v2.2.0
     */
    private function __construct()
    {
    }
}
