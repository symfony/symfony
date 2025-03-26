<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\DataCollector;

use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapterEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @final
 */
class CacheDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var TraceableAdapter[]
     */
    private array $instances = [];

    public function addInstance(string $name, TraceableAdapter $instance): void
    {
        $this->instances[$name] = $instance;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->lateCollect();
    }

    public function reset(): void
    {
        $this->data = [];
        foreach ($this->instances as $instance) {
            $instance->clearCalls();
        }
    }

    public function lateCollect(): void
    {
        $empty = ['calls' => [], 'adapters' => [], 'config' => [], 'options' => [], 'statistics' => []];
        $this->data = ['instances' => $empty, 'total' => $empty];
        foreach ($this->instances as $name => $instance) {
            $this->data['instances']['calls'][$name] = $instance->getCalls();
            $this->data['instances']['adapters'][$name] = get_debug_type($instance->getPool());
        }

        $this->data['instances']['statistics'] = $this->calculateStatistics();
        $this->data['total']['statistics'] = $this->calculateTotalStatistics();
        $this->data['instances']['calls'] = $this->cloneVar($this->data['instances']['calls']);
    }

    public function getName(): string
    {
        return 'cache';
    }

    /**
     * Method returns amount of logged Cache reads: "get" calls.
     */
    public function getStatistics(): array
    {
        return $this->data['instances']['statistics'];
    }

    /**
     * Method returns the statistic totals.
     */
    public function getTotals(): array
    {
        return $this->data['total']['statistics'];
    }

    /**
     * Method returns all logged Cache call objects.
     */
    public function getCalls(): mixed
    {
        return $this->data['instances']['calls'];
    }

    /**
     * Method returns all logged Cache adapter classes.
     */
    public function getAdapters(): array
    {
        return $this->data['instances']['adapters'];
    }

    private function calculateStatistics(): array
    {
        $statistics = [];
        foreach ($this->data['instances']['calls'] as $name => $calls) {
            $statistics[$name] = [
                'calls' => 0,
                'time' => 0,
                'reads' => 0,
                'writes' => 0,
                'deletes' => 0,
                'hits' => 0,
                'misses' => 0,
            ];
            /** @var TraceableAdapterEvent $call */
            foreach ($calls as $call) {
                ++$statistics[$name]['calls'];
                $statistics[$name]['time'] += ($call->end ?? microtime(true)) - $call->start;
                if ('get' === $call->name) {
                    ++$statistics[$name]['reads'];
                    if ($call->hits) {
                        ++$statistics[$name]['hits'];
                    } else {
                        ++$statistics[$name]['misses'];
                        ++$statistics[$name]['writes'];
                    }
                } elseif ('getItem' === $call->name) {
                    ++$statistics[$name]['reads'];
                    if ($call->hits) {
                        ++$statistics[$name]['hits'];
                    } else {
                        ++$statistics[$name]['misses'];
                    }
                } elseif ('getItems' === $call->name) {
                    $statistics[$name]['reads'] += $call->hits + $call->misses;
                    $statistics[$name]['hits'] += $call->hits;
                    $statistics[$name]['misses'] += $call->misses;
                } elseif ('hasItem' === $call->name) {
                    ++$statistics[$name]['reads'];
                    foreach ($call->result ?? [] as $result) {
                        ++$statistics[$name][$result ? 'hits' : 'misses'];
                    }
                } elseif ('save' === $call->name) {
                    ++$statistics[$name]['writes'];
                } elseif ('deleteItem' === $call->name) {
                    ++$statistics[$name]['deletes'];
                }
            }
            if ($statistics[$name]['reads']) {
                $statistics[$name]['hit_read_ratio'] = round(100 * $statistics[$name]['hits'] / $statistics[$name]['reads'], 2);
            } else {
                $statistics[$name]['hit_read_ratio'] = null;
            }
        }

        return $statistics;
    }

    private function calculateTotalStatistics(): array
    {
        $statistics = $this->getStatistics();
        $totals = [
            'calls' => 0,
            'time' => 0,
            'reads' => 0,
            'writes' => 0,
            'deletes' => 0,
            'hits' => 0,
            'misses' => 0,
        ];
        foreach ($statistics as $name => $values) {
            foreach ($totals as $key => $value) {
                $totals[$key] += $statistics[$name][$key];
            }
        }
        if ($totals['reads']) {
            $totals['hit_read_ratio'] = round(100 * $totals['hits'] / $totals['reads'], 2);
        } else {
            $totals['hit_read_ratio'] = null;
        }

        return $totals;
    }
}
