<?php

declare(strict_types=1);

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class ListenerDefinition
{
    public readonly int $priorityModifier;
    public readonly string|null $beforeAfterService;
    public readonly string|null $beforeAfterMethod;

    public function __construct(
        public readonly string $serviceId,
        public readonly string $event,
        public readonly string $method,
        public readonly int $priority,
        public readonly array $dispatchers,
        public readonly string|array|null $before,
        public readonly string|array|null $after,
    )
    {
        if ($before && $after) {
            throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": cannot use "after" and "before" at the same time.', $serviceId));
        }

        if (null === $before && null === $after) {
            $this->priorityModifier = 0;
            $this->beforeAfterMethod = null;
            $this->beforeAfterService = null;

            return;
        }

        $this->priorityModifier = null !== $before ? 1 : -1;

        $beforeAfterDefinition = $before ?? $after;

        if (\is_array($beforeAfterDefinition)) {
            if (!array_is_list($beforeAfterDefinition) || 2 !== \count($beforeAfterDefinition)) {
                throw new InvalidArgumentException(sprintf('Invalid before/after definition for service "%s": when declaring as an array, first item must be a service id and second item must be the method.', $this->serviceId));
            }

            $this->beforeAfterMethod = $beforeAfterDefinition[1];
            $this->beforeAfterService = $beforeAfterDefinition[0];
        } else {
            $this->beforeAfterMethod = null;
            $this->beforeAfterService = $beforeAfterDefinition;
        }
    }

    public function withPriority(int $priority): self
    {
        return new self(
            $this->serviceId,
            $this->event,
            $this->method,
            $priority,
            $this->dispatchers,
            $this->before,
            $this->after,
        );
    }

    public function name(): string
    {
        return "{$this->serviceId}::{$this->method}";
    }

    public function printableBeforeAfterDefinition(): string|null
    {
        return match (true){
            null !== $this->beforeAfterMethod => sprintf('%s::%s()', $this->beforeAfterService, $this->beforeAfterMethod),
            null !== $this->beforeAfterService => $this->beforeAfterService,
            default => null,
        };
    }
}
