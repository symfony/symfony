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
     * @param bool $throwExceptionOnInvalidIndex
     *
     * @return PropertyAccessor The new property accessor
     */
    public static function createPropertyAccessor($throwExceptionOnInvalidIndex = false)
    {
        return self::createPropertyAccessorBuilder($throwExceptionOnInvalidIndex)->getPropertyAccessor();
    }

    /**
     * Creates a property accessor builder.
     *
     * @param bool $enableExceptionOnInvalidIndex
     *
     * @return PropertyAccessorBuilder The new property accessor builder
     */
    public static function createPropertyAccessorBuilder($enableExceptionOnInvalidIndex = false)
    {
        $propertyAccessorBuilder = new PropertyAccessorBuilder();

        if ($enableExceptionOnInvalidIndex) {
            $propertyAccessorBuilder->enableExceptionOnInvalidIndex();
        }

        return $propertyAccessorBuilder;
    }

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}
