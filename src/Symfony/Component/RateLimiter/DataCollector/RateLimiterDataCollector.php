<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\RateLimiter\Debug\TraceableLimiter;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/**
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 *
 * @internal
 */
final class RateLimiterDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private \WeakMap $recorded;
    private \WeakMap $parents;
    private int $nextId = 0;
    private ?Request $request = null;
    private ?array $detailedOperations = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ?\Closure $disabled = null,
    ) {
        $this->recorded = new \WeakMap();
        $this->parents = new \WeakMap();
    }

    public function record(string $name, string $operation, int $tokens, \Closure $call, ?string $key = null, ?string $policy = null): mixed
    {
        if (!($request = $this->requestStack->getCurrentRequest() ?? $this->request) || $this->disabled?->__invoke()) {
            return $call();
        }

        $id = ++$this->nextId;
        $this->parents[$request] ??= [];
        $parent = end($this->parents[$request]) ?: null;
        $this->parents[$request][] = $id;
        $origin = null === $parent ? $this->findOrigin($request) : null;
        $start = microtime(true);
        $result = null;
        $exception = null;
        $failed = false;

        try {
            return $result = $call();
        } catch (\Throwable $e) {
            $exception = ['class' => $e::class, 'message' => $e->getMessage()];
            if ($e instanceof MaxWaitDurationExceededException) {
                $result = $e->getRateLimit();
            } else {
                $failed = true;
            }
            throw $e;
        } finally {
            array_pop($this->parents[$request]);
            $limit = $result instanceof Reservation ? $result->getRateLimit() : ($result instanceof RateLimit ? $result : null);
            $this->recorded[$request] ??= [];
            $this->recorded[$request][] = [
                'id' => $id,
                'parent' => $parent,
                'limiter' => $name,
                'policy' => $policy,
                'key' => $key,
                'operation' => $operation,
                'tokens' => $tokens,
                'duration' => microtime(true) - $start,
                'accepted' => $limit?->isAccepted(),
                'remaining' => $limit?->getRemainingTokens(),
                'limit' => $limit?->getLimit(),
                'unlimited' => $limit && \PHP_INT_MAX === $limit->getLimit() && null === $limit->getResetAt(),
                'resetAt' => $limit?->getResetAt()?->getTimestamp(),
                'retryAfter' => $limit?->getRetryAfter()->getTimestamp(),
                'timeToAct' => $result instanceof Reservation ? $result->getTimeToAct() : null,
                'failed' => $failed,
                'exception' => $exception,
                'origin' => $origin,
            ];
        }
    }

    /**
     * @return array{attribute: bool, controller: ?string, file: ?string, line: ?int}|null
     */
    private function findOrigin(Request $request): ?array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        foreach ($trace as $i => $frame) {
            if (TraceableLimiter::class !== ($frame['class'] ?? null)) {
                continue;
            }

            if (RateLimitAttributeListener::class === ($trace[$i + 1]['class'] ?? null)) {
                $controller = $request->attributes->get('_controller');
                $controller = match (true) {
                    \is_string($controller) => $controller,
                    \is_array($controller) && \is_string($controller[1] ?? null) => (\is_object($controller[0]) ? $controller[0]::class : $controller[0]).'::'.$controller[1],
                    $controller instanceof \Closure => 'Closure',
                    \is_object($controller) => $controller::class.'::__invoke',
                    default => null,
                };

                return ['attribute' => true, 'controller' => $controller, 'file' => null, 'line' => null];
            }

            return isset($frame['file']) ? ['attribute' => false, 'controller' => null, 'file' => $frame['file'], 'line' => $frame['line'] ?? null] : null;
        }

        return null;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->request = $request;
        $this->lateCollect();
    }

    public function lateCollect(): void
    {
        $operations = null !== $this->request ? $this->recorded[$this->request] ?? [] : [];
        usort($operations, static fn (array $a, array $b) => $a['id'] <=> $b['id']);
        $this->data['operations'] = $operations;
        $this->detailedOperations = null;
    }

    public function getName(): string
    {
        return 'rate_limiter';
    }

    public function getOperations(): array
    {
        return $this->data['operations'] ?? [];
    }

    public function getTopLevelCount(): int
    {
        return \count($this->getTopLevelOperations());
    }

    public function getRejectedCount(): int
    {
        return \count(array_filter($this->getTopLevelOperations(), static fn (array $operation) => $operation['rejected']));
    }

    public function getErrorCount(): int
    {
        return \count(array_filter($this->getTopLevelOperations(), static fn (array $operation) => $operation['failed']));
    }

    public function getTotalDuration(): float
    {
        return array_sum(array_column($this->getTopLevelOperations(), 'duration'));
    }

    /**
     * Top-level operations in call order, each with the operations it triggered under "children".
     */
    public function getTimeline(): array
    {
        $operations = $this->getDetailedOperations();
        $nest = static function (array $operation) use (&$nest, $operations): array {
            $operation['children'] = array_map(static fn (int $id) => $nest($operations[$id]), $operation['childIds']);

            return $operation;
        };

        return array_map($nest, array_values($this->getTopLevelOperations()));
    }

    /**
     * Operations grouped by limiter, then by key ("" when no key was given), each with its "children".
     *
     * Limiters called by a compound come right after the first compound calling them.
     *
     * @return array<string, array{name: string, policy: ?string, children: list<string>, keys: array<string, list<array>>}>
     */
    public function getLimiters(): array
    {
        $limiters = [];
        $called = [];
        $add = static function (array $operation) use (&$add, &$limiters, &$called): void {
            $limiter = &$limiters[$operation['limiter']];
            $limiter ??= ['name' => $operation['limiter'], 'policy' => $operation['policy'], 'children' => [], 'keys' => []];
            foreach ($operation['children'] as $child) {
                if (!\in_array($child['limiter'], $limiter['children'], true)) {
                    $limiter['children'][] = $child['limiter'];
                }
            }
            $limiter['keys'][$operation['key'] ?? ''][] = $operation;
            unset($limiter);

            foreach ($operation['children'] as $child) {
                $called[$child['limiter']] = true;
                $add($child);
            }
        };
        foreach ($this->getTimeline() as $operation) {
            $add($operation);
        }

        $sorted = [];
        $place = static function (string $name) use (&$place, &$sorted, $limiters): void {
            if (isset($sorted[$name])) {
                return;
            }
            $sorted[$name] = $limiters[$name];
            array_map($place, $limiters[$name]['children']);
        };
        foreach ($limiters as $name => $limiter) {
            if (!isset($called[$name])) {
                $place($name);
            }
        }

        return $sorted;
    }

    private function getDetailedOperations(): array
    {
        if (null !== $this->detailedOperations) {
            return $this->detailedOperations;
        }

        $operations = [];
        $topLevel = 0;
        foreach ($this->getOperations() as $operation) {
            $parent = null !== $operation['parent'] && isset($operations[$operation['parent']]) ? $operation['parent'] : null;
            $operation['number'] = null !== $parent ? $operations[$parent]['number'].'.'.(\count($operations[$parent]['childIds']) + 1) : (string) ++$topLevel;
            $operation['childIds'] = [];
            $operation['rejected'] = false === $operation['accepted'] && ('reserve' !== $operation['operation'] || null !== ($operation['exception'] ?? null));
            $operations[$operation['id']] = $operation;
            if (null !== $parent) {
                $operations[$parent]['childIds'][] = $operation['id'];
            }
        }

        return $this->detailedOperations = $operations;
    }

    private function getTopLevelOperations(): array
    {
        return array_filter($this->getDetailedOperations(), static fn (array $operation) => null === $operation['parent']);
    }

    public function reset(): void
    {
        $this->data = [];
        $this->detailedOperations = null;
        $this->recorded = new \WeakMap();
        $this->parents = new \WeakMap();
        $this->nextId = 0;
        $this->request = null;
    }
}
