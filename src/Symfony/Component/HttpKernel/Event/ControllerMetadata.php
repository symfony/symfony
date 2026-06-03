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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Provides read-only access to controller metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ControllerMetadata
{
    public function __construct(
        private ControllerEvent $controllerEvent,
    ) {
    }

    public function getController(): callable
    {
        return $this->controllerEvent->getController();
    }

    public function getReflector(): \ReflectionFunctionAbstract
    {
        return $this->controllerEvent->getControllerReflector();
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
        return $this->controllerEvent->getAttributes($className);
    }

    public function evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage): mixed
    {
        return $this->controllerEvent->evaluate($value, $expressionLanguage);
    }
}
