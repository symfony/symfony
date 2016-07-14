<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use Doctrine\Common\Util\ClassUtils;

abstract class ControllerMetadataUtil
{
    /**
     * Gets a class name (if available) and method/function name from a callable.
     *
     * @param callable $controller
     *
     * @return string[]|null the class name and method name (where applicable)
     */
    public static function getControllerLogicalName(callable $controller)
    {
        if ($controller instanceof \Closure) {
            // cannot store any metadata of anonymous functions
            return;
        }

        if (is_object($controller)) {
            // callable class
            $controller = array($controller, '__invoke');
        } elseif (is_string($controller)) {
            // normal function
            $controller = array(null, $controller);
        }

        $className = null;
        $method = $controller[1];

        if (null !== $controller[0]) {
            if (!is_string($controller[0])) {
                $className = class_exists(ClassUtils::class) ? ClassUtils::getClass($controller[0]) : get_class($controller[0]);
            } else {
                $className = $controller[0];
            }
        }

        return array($className, $method);
    }

    private function __construct()
    {
        // private by design
    }
}
