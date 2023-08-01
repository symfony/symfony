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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class WorkflowDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly iterable $workflows,
    ) {
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
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
}
