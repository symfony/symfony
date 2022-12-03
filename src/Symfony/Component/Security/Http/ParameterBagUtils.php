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
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
final class ParameterBagUtils
{
    private static PropertyAccessorInterface $propertyAccessor;

    /**
     * Returns a "parameter" value.
     *
     * Paths like foo[bar] will be evaluated to find deeper items in nested data structures.
     *
     * @throws InvalidArgumentException when the given path is malformed
     */
    public static function getParameterBagValue(ParameterBag $parameters, string $path): mixed
    {
        if (false === $pos = strpos($path, '[')) {
            return $parameters->all()[$path] ?? null;
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $parameters->all()[$root] ?? null) {
            return null;
        }

        self::$propertyAccessor ??= PropertyAccess::createPropertyAccessor();

        try {
            return self::$propertyAccessor->getValue($value, substr($path, $pos));
        } catch (AccessException) {
            return null;
        }
    }

    /**
     * Returns a request "parameter" value.
     *
     * Paths like foo[bar] will be evaluated to find deeper items in nested data structures.
     *
     * @throws InvalidArgumentException when the given path is malformed
     */
    public static function getRequestParameterValue(Request $request, string $path): mixed
    {
        if (false === $pos = strpos($path, '[')) {
            return $request->get($path);
        }

        $root = substr($path, 0, $pos);

        if (null === $value = $request->get($root)) {
            return null;
        }

        self::$propertyAccessor ??= PropertyAccess::createPropertyAccessor();

        try {
            return self::$propertyAccessor->getValue($value, substr($path, $pos));
        } catch (AccessException) {
            return null;
        }
    }
}
