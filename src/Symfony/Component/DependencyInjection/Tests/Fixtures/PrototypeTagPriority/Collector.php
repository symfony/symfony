<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeTagPriority;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class Collector
{
    public function __construct(
        #[AutowireIterator('app.handler')] private iterable $handlers,
    ) {
    }

    public function order(): array
    {
        return array_map(static fn (HandlerInterface $h) => $h->name(), iterator_to_array($this->handlers, false));
    }
}
