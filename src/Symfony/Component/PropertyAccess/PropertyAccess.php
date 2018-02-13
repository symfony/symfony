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
 */
final class PropertyAccess
{
    /**
     * Creates a property accessor with the default configuration.
     *
     * @return PropertyAccessor
     */
    public static function createPropertyAccessor(): PropertyAccessor
    {
        return self::createPropertyAccessorBuilder()->getPropertyAccessor();
    }

    public static function createPropertyAccessorBuilder(): PropertyAccessorBuilder
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
