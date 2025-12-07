<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class FakeEventDispatcher implements EventDispatcherInterface
{
    public array $events = [];
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}
