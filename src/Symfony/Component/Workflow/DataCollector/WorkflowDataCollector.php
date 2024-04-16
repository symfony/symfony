<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DataCollector;

use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class WorkflowDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly iterable $workflows,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileLinkFormatter $fileLinkFormatter,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        foreach ($this->workflows as $workflow) {
            $calls = [];
            if ($workflow instanceof TraceableWorkflow) {
                $calls = $this->cloneVar($workflow->getCalls());
            }

            // We always use a workflow type because we want to mermaid to
            // create a node for transitions
            $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_WORKFLOW);
            $this->data['workflows'][$workflow->getName()] = [
                'dump' => $dumper->dump($workflow->getDefinition()),
                'calls' => $calls,
                'listeners' => $this->getEventListeners($workflow),
            ];
        }
    }

    public function getName(): string
    {
        return 'workflow';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getWorkflows(): array
    {
        return $this->data['workflows'] ?? [];
    }

    public function getCallsCount(): int
    {
        $i = 0;
        foreach ($this->getWorkflows() as $workflow) {
            $i += \count($workflow['calls']);
        }

        return $i;
    }

    protected function getCasters(): array
    {
        $casters = [
            ...parent::getCasters(),
            TransitionBlocker::class => function ($v, array $a, Stub $s, $isNested) {
                unset(
                    $a[sprintf(Caster::PATTERN_PRIVATE, $v::class, 'code')],
                    $a[sprintf(Caster::PATTERN_PRIVATE, $v::class, 'parameters')],
                );

                $s->cut += 2;

                return $a;
            },
            Marking::class => function ($v, array $a, Stub $s, $isNested) {
                $a[Caster::PREFIX_VIRTUAL.'.places'] = array_keys($v->getPlaces());

                return $a;
            },
        ];

        return $casters;
    }

    public function hash(string $string): string
    {
        return hash('xxh128', $string);
    }

    private function getEventListeners(WorkflowInterface $workflow): array
    {
        $listeners = [];
        $placeId = 0;
        foreach ($workflow->getDefinition()->getPlaces() as $place) {
            $eventNames = [];
            $subEventNames = [
                'leave',
                'enter',
                'entered',
            ];
            foreach ($subEventNames as $subEventName) {
                $eventNames[] = sprintf('workflow.%s', $subEventName);
                $eventNames[] = sprintf('workflow.%s.%s', $workflow->getName(), $subEventName);
                $eventNames[] = sprintf('workflow.%s.%s.%s', $workflow->getName(), $subEventName, $place);
            }
            foreach ($eventNames as $eventName) {
                foreach ($this->eventDispatcher->getListeners($eventName) as $listener) {
                    $listeners["place{$placeId}"][$eventName][] = $this->summarizeListener($listener);
                }
            }

            ++$placeId;
        }

        foreach ($workflow->getDefinition()->getTransitions() as $transitionId => $transition) {
            $eventNames = [];
            $subEventNames = [
                'guard',
                'transition',
                'completed',
                'announce',
            ];
            foreach ($subEventNames as $subEventName) {
                $eventNames[] = sprintf('workflow.%s', $subEventName);
                $eventNames[] = sprintf('workflow.%s.%s', $workflow->getName(), $subEventName);
                $eventNames[] = sprintf('workflow.%s.%s.%s', $workflow->getName(), $subEventName, $transition->getName());
            }
            foreach ($eventNames as $eventName) {
                foreach ($this->eventDispatcher->getListeners($eventName) as $listener) {
                    $listeners["transition{$transitionId}"][$eventName][] = $this->summarizeListener($listener, $eventName, $transition);
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
            if (str_contains($r->name, '{closure')) {
                $title = (string) $r;
            } elseif ($class = \PHP_VERSION_ID >= 80111 ? $r->getClosureCalledClass() : $r->getClosureScopeClass()) {
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
            $file = $this->fileLinkFormatter->format($r->getFileName(), $r->getStartLine());
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
