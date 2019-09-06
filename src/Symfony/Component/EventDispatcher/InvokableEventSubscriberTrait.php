<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * Helper trait for single-event subscription.
 *
 * By default, the type of the first argument of the "__invoke" method
 * defines which event should be listened for. This can be overridden
 * by defining a "protected static $listenerEvent" property.
 *
 * A "protected static $listenerPriority" property can also be defined
 * to change the priority of the listener.
 *
 * Don't forget to declare "implements EventSubscriberInterface" when
 * using this trait.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
trait InvokableEventSubscriberTrait
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (!$eventName = isset(static::$listenerEvent) ? static::$listenerEvent : null) {
            $method = new \ReflectionMethod(static::class, '__invoke');

            if (0 === $method->getNumberOfParameters()) {
                throw new \LogicException(sprintf('The Event name cannot be inferred from "%s::%s()" method without parameters, you must define one and type-hint the event class.', static::class, $method->getName()));
            }

            $parameter = $method->getParameters()[0];

            if (null === $type = $parameter->getType()) {
                throw new \LogicException(sprintf('The Event name cannot be inferred from "%s::%s()" method without type-hint the "$%s" parameter, you must type-hint the event class.', static::class, $method->getName(), $parameter->getName()));
            }

            if ($type->isBuiltin()) {
                throw new \LogicException(sprintf('The "$%s" parameter defined in "%s::%s()" method must be a class, "%s" given.', $parameter->getName(), static::class, $method->getName(), $type->getName()));
            }

            $eventName = $type->getName();
        }

        return [$eventName => ['__invoke', isset(static::$listenerPriority) ? static::$listenerPriority : 0]];
    }
}
