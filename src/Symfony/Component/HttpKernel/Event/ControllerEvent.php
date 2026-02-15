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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Allows filtering of a controller callable.
 *
 * You can call getController() to retrieve the current controller. With
 * setController() you can set a new controller that is used in the processing
 * of the request.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ControllerEvent extends KernelEvent
{
    private string|array|object $controller;
    private \ReflectionFunctionAbstract $controllerReflector;

    public function __construct(HttpKernelInterface $kernel, callable $controller, Request $request, ?int $requestType)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setController($controller);
    }

    public function getController(): callable
    {
        return $this->controller;
    }

    public function getControllerReflector(): \ReflectionFunctionAbstract
    {
        return $this->controllerReflector;
    }

    /**
     * @param list<object>|null $attributes
     */
    public function setController(callable $controller, ?array $attributes = null): void
    {
        if (null !== $attributes) {
            if (!array_is_list($flattenAttributes = $attributes)) {
                trigger_deprecation('symfony/http-kernel', '8.1', 'Passing an array of attributes grouped by class name to "%s()" is deprecated. Pass a flat list of attributes instead.', __METHOD__);
                $flattenAttributes = [];
                foreach ($attributes as $attributes) {
                    foreach (\is_array($attributes) ? $attributes : [$attributes] as $attribute) {
                        $flattenAttributes[] = $attribute;
                    }
                }
            }
            $this->getRequest()->attributes->set('_controller_attributes', $flattenAttributes);
        }

        if (isset($this->controller) && ($controller instanceof \Closure ? $controller == $this->controller : $controller === $this->controller)) {
            $this->controller = $controller;

            return;
        }

        if (null === $attributes) {
            $this->getRequest()->attributes->remove('_controller_attributes');
        }

        $this->controllerReflector = match (true) {
            \is_array($controller) && method_exists(...$controller) => new \ReflectionMethod(...$controller),
            \is_string($controller) && str_contains($controller, '::') => new \ReflectionMethod(...explode('::', $controller, 2)),
            default => new \ReflectionFunction($controller(...)),
        };

        $this->controller = $controller;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|'*'|null $className
     *
     * @return ($className is null ? array<class-string, list<object>> : ($className is '*' ? list<object> : list<T>))
     */
    public function getAttributes(?string $className = null): array
    {
        if (null === $attributes = $this->getRequest()->attributes->get('_controller_attributes')) {
            $class = match (true) {
                \is_array($this->controller) && method_exists(...$this->controller) => new \ReflectionClass($this->controller[0]),
                \is_string($this->controller) && false !== $i = strpos($this->controller, '::') => new \ReflectionClass(substr($this->controller, 0, $i)),
                $this->controllerReflector instanceof \ReflectionFunction => $this->controllerReflector->isAnonymous() ? null : $this->controllerReflector->getClosureCalledClass(),
            };
            $attributes = [];

            foreach (array_merge($class?->getAttributes() ?? [], $this->controllerReflector->getAttributes()) as $attribute) {
                if (class_exists($attribute->getName())) {
                    $attributes[] = $attribute->newInstance();
                }
            }

            $this->getRequest()->attributes->set('_controller_attributes', $attributes);
        }

        if ('*' === $className) {
            return $attributes;
        }

        if (null !== $className) {
            return array_values(array_filter($attributes, static fn ($attr) => $attr instanceof $className));
        }

        $grouped = [];
        foreach ($attributes as $attribute) {
            $grouped[$attribute::class][] = $attribute;
        }

        return $grouped;
    }

    public function evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage, array $args = []): mixed
    {
        if (!$value instanceof \Closure && !$value instanceof Expression) {
            return $value;
        }

        $controller = $this->getController();
        $controller = match (true) {
            \is_object($controller) && !$controller instanceof \Closure => $controller,
            \is_array($controller) && \is_object($controller[0]) => $controller[0],
            default => null,
        };

        if ($value instanceof \Closure) {
            return $value($args, $this->getRequest(), $controller);
        }

        if (!$expressionLanguage) {
            throw new \LogicException('Cannot evaluate Expression for controllers since no ExpressionLanguage service was configured.');
        }

        return $expressionLanguage->evaluate($value, [
            'request' => $this->getRequest(),
            'args' => $args,
            'this' => $controller,
        ]);
    }
}
