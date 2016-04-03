<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Responsible for the creation of the action arguments.
 *
 * @deprecated This class is deprecated since 3.1 and will be removed in 4.0. Please use the ArgumentResolver::class instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LegacyArgumentResolver implements ArgumentResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        // only trigger the deprecation notice if actually used, the ControllerResolver still extends it for BC
        @trigger_error(sprintf('The %s class is deprecated since 3.1 and will be removed in 4.0. Please use the %s instead.', __CLASS__, ArgumentResolver::class), E_USER_DEPRECATED);

        if (is_array($controller)) {
            $r = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $r = new \ReflectionObject($controller);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new \ReflectionFunction($controller);
        }

        return $this->doGetArguments($request, $controller, $r->getParameters());
    }

    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        $attributes = $request->attributes->all();
        $arguments = array();
        foreach ($parameters as $param) {
            if (array_key_exists($param->name, $attributes)) {
                if (PHP_VERSION_ID >= 50600 && $param->isVariadic() && is_array($attributes[$param->name])) {
                    $arguments = array_merge($arguments, array_values($attributes[$param->name]));
                } else {
                    $arguments[] = $attributes[$param->name];
                }
            } elseif ($param->getClass() && $param->getClass()->isInstance($request)) {
                $arguments[] = $request;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value or because there is a non optional argument after this one).', $repr, $param->name));
            }
        }

        return $arguments;
    }
}
