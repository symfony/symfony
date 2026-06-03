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
use Symfony\Component\Workflow\Debug\ListenerExtractor;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class WorkflowDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private readonly ListenerExtractor $listenerExtractor;

    public function __construct(
        private readonly iterable $workflows,
        EventDispatcherInterface $eventDispatcher,
        ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
        $this->listenerExtractor = new ListenerExtractor($eventDispatcher, $fileLinkFormatter);
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

    public function hash(string $string): string
    {
        return hash('xxh128', $string);
    }

    public function buildMermaidLiveLink(string $name): string
    {
        $payload = [
            'code' => $this->data['workflows'][$name]['dump'],
            'mermaid' => '{"theme": "default"}',
            'autoSync' => false,
        ];

        $compressed = zlib_encode(json_encode($payload), \ZLIB_ENCODING_DEFLATE);

        $suffix = rtrim(strtr(base64_encode($compressed), '+/', '-_'), '=');

        return "https://mermaid.live/edit#pako:{$suffix}";
    }

    protected function getCasters(): array
    {
        return [
            ...parent::getCasters(),
            TransitionBlocker::class => static function ($v, array $a, Stub $s) {
                unset($a[\sprintf(Caster::PATTERN_PRIVATE, $v::class, 'code')]);
                unset($a[\sprintf(Caster::PATTERN_PRIVATE, $v::class, 'parameters')]);

                $s->cut += 2;

                return $a;
            },
            Marking::class => static function ($v, array $a) {
                $a[Caster::PREFIX_VIRTUAL.'.places'] = array_keys($v->getPlaces());

                return $a;
            },
        ];
    }

    private function getEventListeners(WorkflowInterface $workflow): array
    {
        $listeners = $this->listenerExtractor->extractListeners($workflow->getName(), $workflow->getDefinition());
        $normalizedListeners = [];
        $placeId = 0;
        foreach ($workflow->getDefinition()->getPlaces() as $k => $_) {
            if (\array_key_exists('place__'.$k, $listeners)) {
                $normalizedListeners["place{$placeId}"] = $listeners['place__'.$k];
            }
            ++$placeId;
        }
        foreach ($workflow->getDefinition()->getTransitions() as $k => $_) {
            if (\array_key_exists('transition__'.$k, $listeners)) {
                $normalizedListeners["transition$k"] = $listeners['transition__'.$k];
            }
        }

        return $normalizedListeners;
    }
}
