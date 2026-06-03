<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Debug;

use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Transition;

/**
 * @internal
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class ListenerExtractor
{
    public function __construct(
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
    }

    public function extractListeners(string $name, Definition $definition): array
    {
        if (!$this->dispatcher) {
            return [];
        }

        $listeners = [];
        foreach ($definition->getPlaces() as $placeId => $place) {
            $eventNames = [];
            $subEventNames = [
                'leave',
                'enter',
                'entered',
            ];
            foreach ($subEventNames as $subEventName) {
                $eventNames[] = \sprintf('workflow.%s', $subEventName);
                $eventNames[] = \sprintf('workflow.%s.%s', $name, $subEventName);
                $eventNames[] = \sprintf('workflow.%s.%s.%s', $name, $subEventName, $place);
            }
            foreach ($eventNames as $eventName) {
                foreach ($this->dispatcher->getListeners($eventName) as $listener) {
                    $listeners["place__$placeId"][$eventName][] = $this->summarizeListener($listener);
                }
            }
        }

        foreach ($definition->getTransitions() as $transitionId => $transition) {
            $eventNames = [];
            $subEventNames = [
                'guard',
                'transition',
                'completed',
                'announce',
            ];
            foreach ($subEventNames as $subEventName) {
                $eventNames[] = \sprintf('workflow.%s', $subEventName);
                $eventNames[] = \sprintf('workflow.%s.%s', $name, $subEventName);
                $eventNames[] = \sprintf('workflow.%s.%s.%s', $name, $subEventName, $transition->getName());
            }
            foreach ($eventNames as $eventName) {
                foreach ($this->dispatcher->getListeners($eventName) as $listener) {
                    $listeners["transition__{$transitionId}"][$eventName][] = $this->summarizeListener($listener, $eventName, $transition);
                }
            }
        }

        return $listeners;
    }

    private function summarizeListener(callable $callable, ?string $eventName = null, ?Transition $transition = null): array
    {
        $extra = [];

        if ($callable instanceof \Closure) {
            $r = new \ReflectionFunction($callable);
            if ($r->isAnonymous()) {
                $title = (string) $r;
            } elseif ($class = $r->getClosureCalledClass()) {
                $title = $class->name.'::'.$r->name.'()';
            } else {
                $title = $r->name;
            }
        } elseif (\is_string($callable)) {
            $title = $callable.'()';
            $r = new \ReflectionFunction($callable);
        } elseif (\is_object($callable) && method_exists($callable, '__invoke')) {
            $r = new \ReflectionMethod($callable, '__invoke');
            $title = $callable::class.'::__invoke()';
        } elseif (\is_array($callable)) {
            if ($callable[0] instanceof GuardListener) {
                if (null === $eventName || null === $transition) {
                    throw new \LogicException('Missing event name or transition.');
                }
                $extra['guardExpressions'] = $this->extractGuardExpressions($callable[0], $eventName, $transition);
            }
            $r = new \ReflectionMethod($callable[0], $callable[1]);
            $title = (\is_string($callable[0]) ? $callable[0] : \get_class($callable[0])).'::'.$callable[1].'()';
        } else {
            throw new \RuntimeException('Unknown callable type.');
        }

        $file = null;
        if ($r->isUserDefined()) {
            $file = $this->fileLinkFormatter?->format($r->getFileName(), $r->getStartLine());
        }

        return [
            'title' => $title,
            'file' => $file,
            ...$extra,
        ];
    }

    private function extractGuardExpressions(GuardListener $listener, string $eventName, Transition $transition): array
    {
        $configuration = (new \ReflectionProperty(GuardListener::class, 'configuration'))->getValue($listener);

        $expressions = [];
        foreach ($configuration[$eventName] as $guard) {
            if ($guard instanceof GuardExpression) {
                if ($guard->getTransition() !== $transition) {
                    continue;
                }
                $expressions[] = $guard->getExpression();
            } else {
                $expressions[] = $guard;
            }
        }

        return $expressions;
    }
}
