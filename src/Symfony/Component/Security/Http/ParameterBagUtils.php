<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @internal
 */
final class ParameterBagUtils
{
    private static $propertyAccessor;

    /**
     * Returns a "parameter" value.
     *
     * Paths like foo[bar] will be evaluated to find deeper items in nested data structures.
     *
     * @param string $path The key
     *
     * @return mixed
     *
     * @throws InvalidArgumentException when the given path is malformed
     */
    public static function getParameterBagValue(ParameterBag $parameters, $path)
    {
        if (false === $pos = strpos($path, '[')) {
            return $parameters->get($path);
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $parameters->get($root)) {
            return null;
        }

        if (null === self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        try {
            return self::$propertyAccessor->getValue($value, substr($path, $pos));
        } catch (AccessException $e) {
            return null;
        }
    }

    /**
     * Returns a request "parameter" value.
     *
     * Paths like foo[bar] will be evaluated to find deeper items in nested data structures.
     *
     * @param string $path The key
     *
     * @return mixed
     *
     * @throws InvalidArgumentException when the given path is malformed
     */
    public static function getRequestParameterValue(Request $request, $path)
    {
        if (false === $pos = strpos($path, '[')) {
            return $request->get($path);
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $request->get($root)) {
            return null;
        }

        if (null === self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        try {
            return self::$propertyAccessor->getValue($value, substr($path, $pos));
        } catch (AccessException $e) {
            return null;
        }
    }
}
