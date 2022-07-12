<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Allows filtering of a controller callable.
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in the processing
 * of the request.
 *
 * Controllers should be callables.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ControllerEvent extends KernelEvent
{
    private string|array|object $controller;
    private array $attributes;

    public function __construct(HttpKernelInterface $kernel, callable $controller, Request $request, ?int $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setController($controller);
    }

    public function getController(): callable
    {
        return $this->controller;
    }

    /**
     * @param array<class-string, list<object>>|null $attributes
     */
    public function setController(callable $controller, array $attributes = null): void
    {
        if (null !== $attributes) {
            $this->attributes = $attributes;
        }

        if (isset($this->controller) && ($controller instanceof \Closure ? $controller == $this->controller : $controller === $this->controller)) {
            $this->controller = $controller;

            return;
        }

        if (null === $attributes) {
            unset($this->attributes);
        }

        if (\is_array($controller) && method_exists(...$controller)) {
            $action = new \ReflectionMethod(...$controller);
            $class = new \ReflectionClass($controller[0]);
        } elseif (\is_string($controller) && false !== $i = strpos($controller, '::')) {
            $action = new \ReflectionMethod($controller);
            $class = new \ReflectionClass(substr($controller, 0, $i));
        } else {
            $action = new \ReflectionFunction($controller(...));
            $class = str_contains($action->name, '{closure}') ? null : $action->getClosureScopeClass();
        }

        $this->getRequest()->attributes->set('_controller_reflectors', [$class, $action]);
        $this->controller = $controller;
    }

    /**
     * @return array<class-string, list<object>>
     */
    public function getAttributes(): array
    {
        if (isset($this->attributes) || ![$class, $action] = $this->getRequest()->attributes->get('_controller_reflectors')) {
            return $this->attributes ??= [];
        }

        $this->attributes = [];

        foreach (array_merge($class?->getAttributes() ?? [], $action->getAttributes()) as $attribute) {
            if (class_exists($attribute->getName())) {
                $this->attributes[$attribute->getName()][] = $attribute->newInstance();
            }
        }

        return $this->attributes;
    }
}
