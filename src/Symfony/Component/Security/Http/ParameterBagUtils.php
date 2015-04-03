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
     * @param ParameterBag $parameters The parameter bag
     * @param string       $path       The key
     * @param mixed        $default    The default value if the parameter key does not exist
     *
     * @return mixed
     */
    public static function getParameterBagValue(ParameterBag $parameters, $path, $default = null)
    {
        if (false === $pos = strpos($path, '[')) {
            return $parameters->get($path, $default);
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $parameters->get($root)) {
            return $default;
        }

        if (null === self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return self::$propertyAccessor->getValue($value, substr($path, $pos));
    }

    /**
     * Returns a request "parameter" value.
     *
     * Paths like foo[bar] will be evaluated to find deeper items in nested data structures.
     *
     * @param Request $request The request
     * @param string  $path    The key
     *
     * @return mixed
     */
    public static function getRequestParameterValue(Request $request, $path)
    {
        if (false === $pos = strpos($path, '[')) {
            return $request->get($path);
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $request->get($root)) {
            return;
        }

        if (null === self::$propertyAccessor) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return self::$propertyAccessor->getValue($value, substr($path, $pos));
    }
}
