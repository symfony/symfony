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

use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Event dispatched for each controller attribute.
 *
 * @template T of object
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ControllerAttributeEvent implements StoppableEventInterface
{
    private string|array|object|null $controller;

    /**
     * @param T $attribute
     */
    public function __construct(
        /** @var T */
        public readonly object $attribute,
        public readonly KernelEvent $kernelEvent,
        private readonly ?ExpressionLanguage $expressionLanguage = null,
    ) {
        $this->controller = match (true) {
            $kernelEvent instanceof ControllerEvent => $kernelEvent->getController(),
            $kernelEvent instanceof ControllerArgumentsEvent => $kernelEvent->getController(),
            default => null,
        };
    }

    public function isPropagationStopped(): bool
    {
        $event = $this->kernelEvent;

        if ($event->isPropagationStopped()) {
            return true;
        }

        if (!$this->controller) {
            return false;
        }

        $controller = match (true) {
            $event instanceof ControllerEvent => $event->getController(),
            $event instanceof ControllerArgumentsEvent => $event->getController(),
        };

        return $controller instanceof \Closure ? $controller != $this->controller : $controller !== $this->controller;
    }

    public function evaluate(mixed $value, ?ExpressionLanguage $expressionLanguage = null): mixed
    {
        if (!$value instanceof \Closure && !$value instanceof Expression) {
            return $value;
        }

        $event = $this->kernelEvent;
        $expressionLanguage ??= $this->expressionLanguage;

        return match (true) {
            $event instanceof ControllerEvent => $event->evaluate($value, $expressionLanguage),
            $event instanceof ControllerArgumentsEvent => $event->evaluate($value, $expressionLanguage),
            ($m = $event->controllerMetadata ?? null) instanceof ControllerMetadata => $m->evaluate($value, $expressionLanguage),
        };
    }
}
