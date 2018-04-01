<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Routing;

use Symphony\Component\Routing\Loader\AnnotationClassLoader;
use Symphony\Component\Routing\Route;

/**
 * AnnotatedRouteControllerLoader is an implementation of AnnotationClassLoader
 * that sets the '_controller' default based on the class and method names.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class AnnotatedRouteControllerLoader extends AnnotationClassLoader
{
    /**
     * Configures the _controller default parameter of a given Route instance.
     *
     * @param mixed $annot The annotation class instance
     */
    protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, $annot)
    {
        if ('__invoke' === $method->getName()) {
            $route->setDefault('_controller', $class->getName());
        } else {
            $route->setDefault('_controller', $class->getName().'::'.$method->getName());
        }
    }

    /**
     * Makes the default route name more sane by removing common keywords.
     *
     * @return string
     */
    protected function getDefaultRouteName(\ReflectionClass $class, \ReflectionMethod $method)
    {
        return preg_replace(array(
            '/(bundle|controller)_/',
            '/action(_\d+)?$/',
            '/__/',
        ), array(
            '_',
            '\\1',
            '_',
        ), parent::getDefaultRouteName($class, $method));
    }
}
