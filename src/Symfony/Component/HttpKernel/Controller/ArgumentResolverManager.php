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
 * The ArgumentResolverManager chains over the registered argument resolvers to
 * resolve all controller arguments.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class ArgumentResolverManager
{
    /**
     * @var ArgumentResolverInterface[]
     */
    protected $resolvers = array();

    /**
     * Adds an argument resolver.
     *
     * @param ArgumentResolverInterface $resolver
     */
    public function addResolver(ArgumentResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * Returns the arguments to pass to the controller.
     *
     * @param Request  $request    A Request instance
     * @param callable $controller A PHP callable
     *
     * @return array an array of arguments to pass to the controller
     *
     * @throws \RuntimeException When a parameter cannot be resolved
     */
    public function getArguments(Request $request, $controller)
    {
        if (is_array($controller)) {
            $controllerReflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $controllerReflection = new \ReflectionObject($controller);
            $controllerReflection = $controllerReflection->getMethod('__invoke');
        } else {
            $controllerReflection = new \ReflectionFunction($controller);
        }

        $parameters = $controllerReflection->getParameters();
        $arguments  = array();

        foreach ($parameters as $parameter) {
            foreach ($this->resolvers as $argumentResolver) {
                if ($argumentResolver->accepts($request, $parameter)) {
                    $arguments[] = $argumentResolver->resolve($request, $parameter);
                    continue 2;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                if (is_array($controller)) {
                    $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                } elseif (is_object($controller)) {
                    $repr = get_class($controller);
                } else {
                    $repr = $controller;
                }

                throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument (because there is no default value and none of the argument resolvers could resolve its value).', $repr, $parameter->name));
            }
        }

        return $arguments;
    }
}
