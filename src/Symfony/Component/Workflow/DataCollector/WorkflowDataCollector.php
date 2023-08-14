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
use Symfony\Component\Workflow\Dumper\MermaidDumper;

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
            // We always use a workflow type because we want to mermaid to
            // create a node for transitions
            $dumper = new MermaidDumper(MermaidDumper::TRANSITION_TYPE_WORKFLOW);
            $this->data['workflows'][$workflow->getName()] = [
                'dump' => $dumper->dump($workflow->getDefinition()),
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
}
