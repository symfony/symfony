<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

use Symfony\Component\Security\Acl\Util\ClassUtils as AclClassUtils;

@trigger_error('The '.__NAMESPACE__.'\ClassUtils class is deprecated since Symfony 2.8, to be removed in 3.0. Use Symfony\Component\Security\Acl\Util\ClassUtils instead.', E_USER_DEPRECATED);

/**
 * Class related functionality for objects that
 * might or might not be proxy objects at the moment.
 *
 * @deprecated ClassUtils is deprecated since version 2.8, to be removed in 3.0. Use Acl ClassUtils instead.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Johannes Schmitt <schmittjoh@gmail.com>
 */
class ClassUtils
{
    /**
     * Marker for Proxy class names.
     */
    const MARKER = '__CG__';

    /**
     * Length of the proxy marker.
     */
    const MARKER_LENGTH = 6;

    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Gets the real class name of a class name that could be a proxy.
     *
     * @param string|object $object
     *
     * @return string
     */
    public static function getRealClass($object)
    {
        if (class_exists('Symfony\Component\Security\Acl\Util\ClassUtils')) {
            return AclClassUtils::getRealClass($object);
        }

        // fallback in case security-acl is not installed
        $class = \is_object($object) ? \get_class($object) : $object;

        if (false === $pos = strrpos($class, '\\'.self::MARKER.'\\')) {
            return $class;
        }

        return substr($class, $pos + self::MARKER_LENGTH + 2);
    }
}
