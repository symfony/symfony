<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @final
 */
class SerializerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private const DATA_TEMPLATE = [
        'serialize' => [],
        'deserialize' => [],
        'normalize' => [],
        'denormalize' => [],
        'encode' => [],
        'decode' => [],
    ];

    private array $dataGroupedByName;
    private array $collected = [];

    public function reset(): void
    {
        $this->data = [];
        unset($this->dataGroupedByName);
        $this->collected = [];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Everything is collected during the request, and formatted on kernel terminate.
    }

    public function getName(): string
    {
        return 'serializer';
    }

    public function getData(?string $name = null): Data|array
    {
        return null === $name ? $this->data : $this->getDataGroupedByName()[$name];
    }

    public function getHandledCount(?string $name = null): int
    {
        return array_sum(array_map('count', $this->getData($name)));
    }

    public function getTotalTime(): float
    {
        $totalTime = 0;

        foreach ($this->data as $handled) {
            $totalTime += array_sum(array_map(static fn (array $el): float => $el['time'], $handled));
        }

        return $totalTime;
    }

    public function getSerializerNames(): array
    {
        return array_keys($this->getDataGroupedByName());
    }

    public function collectSerialize(string $traceId, mixed $data, string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'serialize'],
        );
    }

    public function collectDeserialize(string $traceId, mixed $data, string $type, string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'type', 'context', 'time', 'caller', 'name'),
            ['method' => 'deserialize'],
        );
    }

    public function collectNormalize(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'normalize'],
        );
    }

    public function collectDenormalize(string $traceId, mixed $data, string $type, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'type', 'context', 'time', 'caller', 'name'),
            ['method' => 'denormalize'],
        );
    }

    public function collectEncode(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'encode'],
        );
    }

    public function collectDecode(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'decode'],
        );
    }

    public function collectNormalization(string $traceId, string $normalizer, float $time, string $name): void
    {
        $this->collectNestedCall($this->collected[$traceId]['normalization'], $normalizer, 'normalize', $time);
    }

    public function collectDenormalization(string $traceId, string $normalizer, float $time, string $name): void
    {
        $this->collectNestedCall($this->collected[$traceId]['normalization'], $normalizer, 'denormalize', $time);
    }

    public function collectEncoding(string $traceId, string $encoder, float $time, string $name): void
    {
        $this->collectNestedCall($this->collected[$traceId]['encoding'], $encoder, 'encode', $time);
    }

    public function collectDecoding(string $traceId, string $encoder, float $time, string $name): void
    {
        $this->collectNestedCall($this->collected[$traceId]['encoding'], $encoder, 'decode', $time);
    }

    public function lateCollect(): void
    {
        $this->data = self::DATA_TEMPLATE;

        foreach ($this->collected as $collected) {
            if (!isset($collected['data'])) {
                continue;
            }

            $data = [
                'data' => $this->cloneVar($collected['data']),
                'dataType' => get_debug_type($collected['data']),
                'type' => $collected['type'] ?? null,
                'format' => $collected['format'],
                'time' => $collected['time'],
                'context' => $this->cloneVar($collected['context']),
                'normalization' => [],
                'encoding' => [],
                'caller' => $collected['caller'] ?? null,
                'name' => $collected['name'],
            ];

            if (isset($collected['normalization'])) {
                [$data['normalizer'], $data['normalization']] = $this->getNestedCalls($collected['normalization']);
            }

            if (isset($collected['encoding'])) {
                [$data['encoder'], $data['encoding']] = $this->getNestedCalls($collected['encoding']);
            }

            $this->data[$collected['method']][] = $data;
        }
    }

    private function getDataGroupedByName(): array
    {
        if (!isset($this->dataGroupedByName)) {
            $this->dataGroupedByName = [];

            foreach ($this->data as $method => $items) {
                foreach ($items as $item) {
                    $this->dataGroupedByName[$item['name']] ??= self::DATA_TEMPLATE;
                    $this->dataGroupedByName[$item['name']][$method][] = $item;
                }
            }
        }

        return $this->dataGroupedByName;
    }

    /**
     * Aggregates the calls per class, except the last one.
     *
     * Nested calls end before the call that wraps them, so the last call is the outermost one.
     *
     * @param-out array $calls
     */
    private function collectNestedCall(?array &$calls, string $class, string $method, float $time): void
    {
        if (isset($calls['last'])) {
            [$lastClass, $lastMethod, $lastTime] = $calls['last'];
            $nested = &$calls['nested'][$lastClass];
            $nested ??= ['time' => 0, 'calls' => 0, 'method' => $lastMethod];
            $nested['time'] += $lastTime;
            ++$nested['calls'];
        }

        $calls['last'] = [$class, $method, $time];
    }

    private function getNestedCalls(array $calls): array
    {
        [$class, $method, $time] = $calls['last'];
        $main = ['time' => $time] + $this->getMethodLocation($class, $method);
        $nested = [];

        foreach ($calls['nested'] ?? [] as $class => $call) {
            $nested[$class] = ['time' => $call['time'], 'calls' => $call['calls']] + $this->getMethodLocation($class, $call['method']);
        }

        return [$main, $nested];
    }

    private function getMethodLocation(string $class, string $method): array
    {
        $reflection = new \ReflectionClass($class);

        return [
            'class' => $reflection->getShortName(),
            'file' => $reflection->getFileName(),
            'line' => $reflection->getMethod($method)->getStartLine(),
        ];
    }
}
