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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\RecoverableAttribute;
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
     * The failures of the recoverable attributes handled before the arguments are resolved, with the exceptions their attribute declares.
     *
     * @var \WeakMap<Request, list<array{\Throwable, list<class-string<\Throwable>>}>>
     */
    private \WeakMap $recoveredFailures;

    /**
     * @param array<string, array<class-string, true>> $attributesWithListenersByEvent
     */
    public function __construct(
        private readonly array $attributesWithListenersByEvent,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
        $this->recoveredFailures = new \WeakMap();
        $this->expressionLanguage ??= class_exists(ExpressionLanguage::class, false) ? new ExpressionLanguage(null, class_exists(ConstantFunctionProvider::class) ? [new ConstantFunctionProvider(['*', '*::*'])] : []) : null;
    }

    private static array $attributeHierarchyCache = [];

    /** @var array<class-string, list<class-string<\Throwable>>> */
    private static array $recoverableExceptionsCache = [];

    /**
     * Attributes are cloned for each request, since listeners can change them.
     *
     * Static since it only holds what the code declares, which is the same for all instances.
     *
     * @var array<string, list<object>>
     */
    private static array $attributesCache = [];

    public function beforeController(ControllerEvent|ControllerArgumentsEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $controller = $event->getController();
        $attributes = $this->getAttributes($event, $controller);
        $swapBudget = \count($attributes) << 3;

        if ($event instanceof ControllerArgumentsEvent) {
            $this->injectRecoveredFailures($event);
        }

        dispatch_attributes:
        foreach ($attributes as $attribute) {
            if (!$attributeEventNames = $this->getAttributeEventNames($attribute, $eventName)) {
                continue;
            }

            foreach ($attributeEventNames as $attributeEventName) {
                try {
                    $dispatcher->dispatch(new ControllerAttributeEvent($attribute, $event, $this->expressionLanguage), $attributeEventName);
                } catch (\Throwable $e) {
                    if (!$this->recover($event, $attribute, $e)) {
                        throw $e;
                    }

                    break;
                }

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
     * Passes the failure of a recoverable attribute to the controller parameter typed to receive it, once the arguments are resolved.
     */
    private function recover(ControllerEvent|ControllerArgumentsEvent $event, object $attribute, \Throwable $exception): bool
    {
        if (!$classes = self::getRecoverableExceptions($attribute::class)) {
            return false;
        }

        if (null === $recoverable = self::findRecoverableException($classes, $exception)) {
            return false;
        }

        if ($event instanceof ControllerArgumentsEvent) {
            return $this->inject($event, $recoverable, $classes);
        }

        if (!array_any($event->getControllerReflector()->getParameters(), static fn (\ReflectionParameter $parameter): bool => self::accepts($parameter, $recoverable, $classes))) {
            return false;
        }

        $request = $event->getRequest();
        $this->recoveredFailures[$request] = [...$this->recoveredFailures[$request] ?? [], [$recoverable, $classes]];

        return true;
    }

    private function injectRecoveredFailures(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $failures = $this->recoveredFailures[$request] ?? [];
        unset($this->recoveredFailures[$request]);

        foreach ($failures as [$failure, $classes]) {
            if (!$this->inject($event, $failure, $classes)) {
                throw $failure;
            }
        }
    }

    /**
     * @param list<class-string<\Throwable>> $recoverableExceptions
     */
    private function inject(ControllerArgumentsEvent $event, \Throwable $exception, array $recoverableExceptions): bool
    {
        $arguments = $event->getArguments();

        foreach ($event->getControllerReflector()->getParameters() as $i => $parameter) {
            if (!\array_key_exists($i, $arguments) || null !== $arguments[$i] || !self::accepts($parameter, $exception, $recoverableExceptions)) {
                continue;
            }

            $arguments[$i] = $exception;
            $event->setArguments($arguments);

            return true;
        }

        return false;
    }

    /**
     * Returns the exception, or the first of its previous ones, that is one of the recoverable exceptions.
     *
     * @param list<class-string<\Throwable>> $classes
     */
    private static function findRecoverableException(array $classes, \Throwable $exception): ?\Throwable
    {
        for ($e = $exception; $e; $e = $e->getPrevious()) {
            if (array_any($classes, static fn (string $class): bool => $e instanceof $class)) {
                return $e;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     *
     * @return list<class-string<\Throwable>>
     */
    private static function getRecoverableExceptions(string $class): array
    {
        if (isset(self::$recoverableExceptionsCache[$class])) {
            return self::$recoverableExceptionsCache[$class];
        }

        $exceptions = [];

        for ($r = new \ReflectionClass($class); $r; $r = $r->getParentClass()) {
            foreach ($r->getAttributes(RecoverableAttribute::class) as $recoverable) {
                array_push($exceptions, ...$recoverable->newInstance()->exceptions);
            }
        }

        return self::$recoverableExceptionsCache[$class] = $exceptions;
    }

    /**
     * @param list<class-string<\Throwable>> $recoverableExceptions
     */
    private static function accepts(\ReflectionParameter $parameter, \Throwable $exception, array $recoverableExceptions): bool
    {
        if ($parameter->isVariadic() || !$type = $parameter->getType()) {
            return false;
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $t) {
            if (!$t instanceof \ReflectionNamedType || $t->isBuiltin() || !is_a($exception, $t->getName())) {
                continue;
            }

            // a broad type like \Exception would also receive failures the controller doesn't expect
            if (array_any($recoverableExceptions, static fn (string $class): bool => is_a($t->getName(), $class, true))) {
                return true;
            }
        }

        return false;
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
        foreach (self::$attributesCache[$key] ??= $event->getAttributes('*') as $attribute) {
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
