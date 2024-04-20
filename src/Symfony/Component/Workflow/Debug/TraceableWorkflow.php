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

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlockerList;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class TraceableWorkflow implements WorkflowInterface
{
    private array $calls = [];

    public function __construct(
        private readonly WorkflowInterface $workflow,
        private readonly Stopwatch $stopwatch,
    ) {
    }

    public function getMarking(object $subject, array $context = []): Marking
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function can(object $subject, string $transitionName): bool
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function apply(object $subject, string $transitionName, array $context = []): Marking
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function getEnabledTransitions(object $subject): array
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function getEnabledTransition(object $subject, string $name): ?Transition
    {
        return $this->callInner(__FUNCTION__, \func_get_args());
    }

    public function getName(): string
    {
        return $this->workflow->getName();
    }

    public function getDefinition(): Definition
    {
        return $this->workflow->getDefinition();
    }

    public function getMarkingStore(): MarkingStoreInterface
    {
        return $this->workflow->getMarkingStore();
    }

    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->workflow->getMetadataStore();
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    private function callInner(string $method, array $args): mixed
    {
        $sMethod = $this->workflow::class.'::'.$method;
        $this->stopwatch->start($sMethod, 'workflow');

        $previousMarking = null;
        if ('apply' === $method) {
            try {
                $previousMarking = $this->workflow->getMarking($args[0]);
            } catch (\Throwable) {
            }
        }

        try {
            $return = $this->workflow->{$method}(...$args);

            $this->calls[] = [
                'method' => $method,
                'duration' => $this->stopwatch->stop($sMethod)->getDuration(),
                'args' => $args,
                'previousMarking' => $previousMarking ?? null,
                'return' => $return,
            ];

            return $return;
        } catch (\Throwable $exception) {
            $this->calls[] = [
                'method' => $method,
                'duration' => $this->stopwatch->stop($sMethod)->getDuration(),
                'args' => $args,
                'previousMarking' => $previousMarking ?? null,
                'exception' => $exception,
            ];

            throw $exception;
        }
    }
}
