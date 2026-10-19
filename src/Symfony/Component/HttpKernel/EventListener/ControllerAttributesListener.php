<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ConstantFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

// Help opcache.preload discover always-needed symbols
class_exists(ControllerAttributeEvent::class);
class_exists(ExpressionLanguage::class);

/**
 * Dispatches events for controller attributes.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ControllerAttributesListener implements EventSubscriberInterface
{
    /**
     * @param array<string, array<class-string, true>> $attributesWithListenersByEvent
     */
    public function __construct(
        private readonly array $attributesWithListenersByEvent,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
        $this->expressionLanguage ??= class_exists(ExpressionLanguage::class, false) ? new ExpressionLanguage(null, class_exists(ConstantFunctionProvider::class) ? [new ConstantFunctionProvider(['*', '*::*'])] : []) : null;
    }

    private static array $attributeHierarchyCache = [];

    /**
     * Attributes are cloned for each request, since listeners can change them.
     *
     * @var array<string, list<object>>
     */
    private array $attributesCache = [];

    public function beforeController(ControllerEvent|ControllerArgumentsEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $controller = $event->getController();
        $attributes = $this->getAttributes($event, $controller);
        $swapBudget = \count($attributes) << 3;

        dispatch_attributes:
        foreach ($attributes as $attribute) {
            if (!$attributeEventNames = $this->getAttributeEventNames($attribute, $eventName)) {
                continue;
            }

            foreach ($attributeEventNames as $attributeEventName) {
                $dispatcher->dispatch(new ControllerAttributeEvent($attribute, $event, $this->expressionLanguage), $attributeEventName);

                if ($event->isPropagationStopped()) {
                    return;
                }
            }

            $c = $event->getController();
            if ($c instanceof \Closure ? $c != $controller : $c !== $controller) {
                if (--$swapBudget < 0) {
                    throw new \LogicException(\sprintf('Controller swap loop detected while dispatching attributes for event "%s"; a listener keeps changing the controller.', $eventName));
                }
                $controller = $c;
                $attributes = $this->getAttributes($event, $c);
                goto dispatch_attributes;
            }
        }
    }

    public function afterController(KernelEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $attributes = $event->controllerMetadata?->getAttributes('*') ?? [];

        for ($i = \count($attributes) - 1; $i >= 0; --$i) {
            $attribute = $attributes[$i];
            $attributeEventNames = $this->getAttributeEventNames($attribute, $eventName);

            for ($j = \count($attributeEventNames) - 1; $j >= 0; --$j) {
                $dispatcher->dispatch(new ControllerAttributeEvent($attribute, $event, $this->expressionLanguage), $attributeEventNames[$j]);

                if ($event->isPropagationStopped()) {
                    return;
                }
            }
        }
    }

    /**
     * @return list<object>
     */
    private function getAttributes(ControllerEvent|ControllerArgumentsEvent $event, callable $controller): array
    {
        $request = $event->getRequest();

        // anonymous closures and Closure::__invoke() have no name that tells them apart, so they are not cached
        $key = match (true) {
            null !== $request->attributes->get('_controller_attributes') => null,
            \is_array($controller) => $controller[0] instanceof \Closure ? null : (\is_object($controller[0]) ? $controller[0]::class : $controller[0]).'::'.$controller[1],
            \is_string($controller) => $controller,
            $controller instanceof \Closure => ($r = new \ReflectionFunction($controller))->isAnonymous() ? null : $r->getClosureCalledClass()?->name.'::'.$r->name.'@'.$r->getClosureScopeClass()?->name,
            \is_object($controller) => $controller::class.'::__invoke',
            default => null,
        };

        if (null === $key) {
            return $event->getAttributes('*');
        }

        $attributes = [];
        foreach ($this->attributesCache[$key] ??= $event->getAttributes('*') as $attribute) {
            $attributes[] = clone $attribute;
        }
        $request->attributes->set('_controller_attributes', $attributes);

        return $attributes;
    }

    private function getAttributeEventNames(object $attribute, string $eventName): array
    {
        if (!$attributesWithListeners = $this->attributesWithListenersByEvent[$eventName] ?? []) {
            return [];
        }

        $names = [];
        $class = $attribute::class;
        $hierarchy = self::$attributeHierarchyCache[$class] ??= [$class => $class] + class_parents($class) + class_implements($class);

        foreach ($hierarchy as $class) {
            if (isset($attributesWithListeners[$class])) {
                $names[] = $eventName.'.'.$class;
            }
        }

        return $names;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['beforeController', -10000],
            KernelEvents::CONTROLLER_ARGUMENTS => ['beforeController', -10000],
            KernelEvents::VIEW => ['afterController', 10000],
            KernelEvents::RESPONSE => ['afterController', 10000],
            KernelEvents::EXCEPTION => ['afterController', 10000],
            KernelEvents::FINISH_REQUEST => ['afterController', 10000],
        ];
    }
}
