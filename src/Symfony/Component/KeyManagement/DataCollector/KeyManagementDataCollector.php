<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Gathers what the debug decorators of the component recorded during a request.
 *
 * What is collected is metadata and nothing else: which key an operation used, how many bytes went
 * in and came back, how long it took. Never a plaintext, never a ciphertext, never a data key, and
 * not the AAD either, of which only the presence is counted.
 *
 * Nothing is kept per call. An entity with ten encrypted columns read over fifty rows makes a
 * thousand operations, which no reader wants listed, and which nobody wants serialized into a
 * profile either. Each call is folded on arrival into three aggregates, so what the collector holds
 * is bounded by the number of call sites and of keys rather than by the number of payloads:
 *
 *   - by caller, which answers what a piece of code costs;
 *   - by key, which answers what a scope or a master key costs, and whether a data key is being
 *     shared as intended;
 *   - by service, which answers which client and which layer did the work.
 *
 * Durations are unioned rather than summed: an envelope call contains the store lookup and the KMS
 * round trip it made, so adding the three would report more time than the request spent.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @final
 *
 * @experimental
 */
class KeyManagementDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public const string LAYER_KMS = 'kms';
    public const string LAYER_ENVELOPE = 'envelope';
    public const string LAYER_STORE = 'store';

    public const string ORIGIN_CORE = 'core';
    public const string ORIGIN_BRIDGE = 'bridge';

    public const string KIND_MASTER_KEY = 'master_key';
    public const string KIND_SCOPE = 'scope';
    public const string KIND_DATA_KEY = 'data_key';

    private const array CALL_DEFAULTS = [
        'layer' => self::LAYER_KMS,
        'operation' => '',
        'service' => '',
        'backend' => null,
        'key' => null,
        'reference' => null,
        'format' => null,
        'aad' => null,
        'deterministic' => null,
        'length' => null,
        'in' => null,
        'out' => null,
        'start' => 0.0,
        'time' => 0.0,
        'error' => null,
        'caller' => null,
    ];

    private const array LAYER_TOTALS = [self::LAYER_KMS => 0, self::LAYER_ENVELOPE => 0, self::LAYER_STORE => 0];

    private const array ORIGIN_TOTALS = [self::ORIGIN_CORE => 0, self::ORIGIN_BRIDGE => 0];

    private const array TOTALS = [
        'ops' => 0,
        'layers' => self::LAYER_TOTALS,
        'origins' => self::ORIGIN_TOTALS,
        'errors' => 0,
        'in' => 0,
        'out' => 0,
        'time' => 0.0,
        'kms_time' => 0.0,
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $callers = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $keys = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $services = [];

    private array $totals = self::TOTALS;

    /**
     * @var list<array{start: float, end: float}>
     */
    private array $segments = [];

    /**
     * Scope each data key seen in this request belongs to, so that reading a payload lands under
     * the scope that wrote it rather than under the reference it names.
     *
     * @var array<string, string>
     */
    private array $scopeOf = [];

    /**
     * @param list<string> $clients Names of the configured KMS clients, so that the panel says what
     *                              is wired even when a request used none of it
     */
    public function __construct(
        private readonly array $clients = [],
    ) {
    }

    /**
     * Folds one call of one decorator into the aggregates.
     *
     * @param array<string, mixed> $call
     *
     * @internal
     */
    public function collectCall(array $call): void
    {
        $call += self::CALL_DEFAULTS;
        $call['origin'] = self::originOf($call['backend']);
        [$call['label'], $call['kind']] = $this->identify($call);

        ++$this->totals['ops'];
        ++$this->totals['layers'][$call['layer']];
        ++$this->totals['origins'][$call['origin']];
        $this->totals['in'] += $call['in'] ?? 0;
        $this->totals['out'] += $call['out'] ?? 0;
        $this->totals['errors'] += null !== $call['error'] ? 1 : 0;
        $this->totals['kms_time'] += self::LAYER_KMS === $call['layer'] ? $call['time'] : 0.0;
        self::accumulate($this->segments, $this->totals['time'], $call);

        $this->collectCaller($call);
        $this->collectKey($call);
        $this->collectService($call);
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data = [
            'callers' => self::finalize($this->callers),
            'keys' => self::finalize($this->keys),
            'services' => array_map(static fn (array $services): array => array_map(self::strip(...), $services), $this->services),
            'clients' => $this->clients,
            'totals' => $this->totals,
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->callers = [];
        $this->keys = [];
        $this->services = [];
        $this->segments = [];
        $this->scopeOf = [];
        $this->totals = self::TOTALS;
    }

    public function getName(): string
    {
        return 'key_management';
    }

    /**
     * Call sites, costliest first.
     *
     * @return list<array<string, mixed>>
     */
    public function getCallers(): array
    {
        return $this->data['callers'] ?? [];
    }

    /**
     * Master keys, scopes and data keys, costliest first.
     *
     * @return list<array<string, mixed>>
     */
    public function getKeys(): array
    {
        return $this->data['keys'] ?? [];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getServices(): array
    {
        return $this->data['services'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getClients(): array
    {
        return $this->data['clients'] ?? [];
    }

    public function getOperationCount(): int
    {
        return $this->data['totals']['ops'] ?? 0;
    }

    /**
     * Round trips to a KMS backend, which is what a request pays for over the network.
     */
    public function getKmsCallCount(): int
    {
        return $this->data['totals']['layers'][self::LAYER_KMS] ?? 0;
    }

    public function getEnvelopeCallCount(): int
    {
        return $this->data['totals']['layers'][self::LAYER_ENVELOPE] ?? 0;
    }

    public function getStoreCallCount(): int
    {
        return $this->data['totals']['layers'][self::LAYER_STORE] ?? 0;
    }

    public function getErrorCount(): int
    {
        return $this->data['totals']['errors'] ?? 0;
    }

    public function getBytesIn(): int
    {
        return $this->data['totals']['in'] ?? 0;
    }

    public function getBytesOut(): int
    {
        return $this->data['totals']['out'] ?? 0;
    }

    /**
     * Wall time of the outermost calls only, so that an envelope operation and the KMS round trip
     * it made under it are not counted twice.
     */
    public function getTotalTime(): float
    {
        return $this->data['totals']['time'] ?? 0.0;
    }

    public function getKmsTime(): float
    {
        return $this->data['totals']['kms_time'] ?? 0.0;
    }

    /**
     * @param array<string, mixed> $call
     */
    private function collectCaller(array $call): void
    {
        $caller = $call['caller'];
        $id = null !== $caller ? $caller['file'].':'.$caller['line'] : '';

        $group = $this->callers[$id] ?? [
            'name' => $caller['name'] ?? 'unknown',
            'file' => $caller['file'] ?? null,
            'line' => $caller['line'] ?? null,
            'keys' => [],
        ] + self::newGroup();

        self::fold($group, $call);
        $group['keys'][$call['label']] = true;

        $this->callers[$id] = $group;
    }

    /**
     * @param array<string, mixed> $call
     */
    private function collectKey(array $call): void
    {
        $group = $this->keys[$call['label']] ?? [
            'kind' => $call['kind'],
            'label' => $call['label'],
            'data_keys' => [],
            'backends' => [],
            'aad' => 0,
            'deterministic' => 0,
        ] + self::newGroup();

        self::fold($group, $call);

        if (null !== $call['backend']) {
            $group['backends'][$call['backend']] = true;
        }
        if (self::KIND_SCOPE === $group['kind'] && null !== $call['reference']) {
            $group['data_keys'][self::printable($call['reference'])] = true;
        }
        if (null !== $call['aad'] && '' !== $call['aad']) {
            ++$group['aad'];
        }
        if (true === $call['deterministic']) {
            ++$group['deterministic'];
        }

        $this->keys[$call['label']] = $group;
    }

    /**
     * @param array<string, mixed> $call
     */
    private function collectService(array $call): void
    {
        $group = $this->services[$call['layer']][$call['service']] ?? ['backend' => $call['backend']] + self::newGroup();

        self::fold($group, $call);

        $this->services[$call['layer']][$call['service']] = $group;
    }

    /**
     * @return array<string, mixed>
     */
    private static function newGroup(): array
    {
        return [
            'ops' => 0,
            'layers' => self::LAYER_TOTALS,
            'operations' => [],
            'origins' => self::ORIGIN_TOTALS,
            'in' => 0,
            'out' => 0,
            'time' => 0.0,
            'kms_time' => 0.0,
            'errors' => 0,
            'error' => null,
            'segments' => [],
        ];
    }

    /**
     * @param array<string, mixed> $group
     * @param array<string, mixed> $call
     */
    private static function fold(array &$group, array $call): void
    {
        ++$group['ops'];
        ++$group['layers'][$call['layer']];
        ++$group['origins'][$call['origin']];
        $group['operations'][$call['operation']] = ($group['operations'][$call['operation']] ?? 0) + 1;
        $group['in'] += $call['in'] ?? 0;
        $group['out'] += $call['out'] ?? 0;
        $group['kms_time'] += self::LAYER_KMS === $call['layer'] ? $call['time'] : 0.0;

        if (null !== $call['error']) {
            ++$group['errors'];
            $group['error'] ??= $call['error'];
        }

        self::accumulate($group['segments'], $group['time'], $call);
    }

    /**
     * Adds what `$call` spent that is not already accounted for.
     *
     * Decorators report a call once it returns, so a nested one is always folded before the call
     * that made it. An arriving call therefore either opens a segment of its own, or swallows the
     * trailing segments it contains, whose time it already includes.
     *
     * @param list<array{start: float, end: float}> $segments
     * @param array<string, mixed>                  $call
     */
    private static function accumulate(array &$segments, float &$total, array $call): void
    {
        $start = (float) $call['start'];
        $end = $start + (float) $call['time'];

        while ($segments && ($last = $segments[array_key_last($segments)])['start'] >= $start && $last['end'] <= $end) {
            array_pop($segments);
            $total -= $last['end'] - $last['start'];
        }

        $segments[] = ['start' => $start, 'end' => $end];
        $total += $end - $start;
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     *
     * @return list<array<string, mixed>>
     */
    private static function finalize(array $groups): array
    {
        $groups = array_map(self::strip(...), $groups);
        usort($groups, static fn (array $a, array $b): int => ($b['time'] <=> $a['time']) ?: ($b['ops'] <=> $a['ops']));

        return $groups;
    }

    /**
     * @param array<string, mixed> $group
     *
     * @return array<string, mixed>
     */
    private static function strip(array $group): array
    {
        unset($group['segments']);

        foreach (['keys', 'data_keys', 'backends'] as $set) {
            if (isset($group[$set])) {
                $group[$set] = array_keys($group[$set]);
            }
        }

        return $group;
    }

    /**
     * What a call is about, from the point of view of someone reading the panel: the master key of
     * a direct operation, the scope a stored data key is shared over, or that data key itself.
     *
     * A stored envelope records a reference and no scope, by design, since the reference is what
     * resolves the key. Whoever writes under a scope teaches this request which scope a reference
     * belongs to, so reading a payload written earlier in the same request joins the scope that
     * wrote it. Nothing having taught it, the reference stands as the label: a read-only request
     * has no other truth to show.
     *
     * @param array<string, mixed> $call
     *
     * @return array{string, string}
     */
    private function identify(array $call): array
    {
        if (null !== $call['key']) {
            $kind = self::LAYER_STORE === $call['layer'] || 'stored' === $call['format'] ? self::KIND_SCOPE : self::KIND_MASTER_KEY;

            if (null !== $call['reference']) {
                $this->scopeOf[$call['reference']] = $call['key'];
            }

            return [self::printable($call['key']), $kind];
        }

        if (null === $call['reference']) {
            return ['(none)', self::KIND_MASTER_KEY];
        }

        return isset($this->scopeOf[$call['reference']])
            ? [$this->scopeOf[$call['reference']], self::KIND_SCOPE]
            : [self::printable($call['reference']), self::KIND_DATA_KEY];
    }

    /**
     * A data key reference is opaque bytes, and the Doctrine store keeps UUIDs in their binary
     * form, so what reaches a template has to be printable first.
     */
    private static function printable(string $value): string
    {
        if (!preg_match('//u', $value) || preg_match('/[\x00-\x08\x0E-\x1F\x7F]/', $value)) {
            $value = bin2hex($value);
        }

        return \strlen($value) > 64 ? substr($value, 0, 64).'...' : $value;
    }

    /**
     * Whether a call was served inside the process or by something outside it. A bridge is what
     * turns an operation into a network round trip, or into a query, which is a different thing to
     * read than the local AEAD of an envelope, so the panel keeps the two apart. Anything the
     * component does not ship is read as a bridge: it is not this process either.
     */
    private static function originOf(?string $backend): string
    {
        return null !== $backend
            && str_starts_with($backend, 'Symfony\\Component\\KeyManagement\\')
            && !str_contains($backend, '\\Bridge\\')
                ? self::ORIGIN_CORE
                : self::ORIGIN_BRIDGE;
    }
}
