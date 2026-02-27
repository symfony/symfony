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
class ControllerArgumentsMetadata extends ControllerMetadata
{
    public function __construct(
        ControllerEvent $controllerEvent,
        private ControllerArgumentsEvent $controllerArgumentsEvent,
    ) {
        parent::__construct($controllerEvent);
    }

    /**
     * @return list<mixed>
     */
    public function getArguments(): array
    {
        return $this->controllerArgumentsEvent->getArguments();
    }

    /**
     * @return array<string, mixed>
     */
    public function getNamedArguments(): array
    {
        return $this->controllerArgumentsEvent->getNamedArguments();
    }

    public function evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage): mixed
    {
        return $this->controllerArgumentsEvent->evaluate($value, $expressionLanguage);
    }
}
