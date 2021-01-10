<?php

namespace Symfony\Component\EventDispatcher\Tests\Fixtures;

use Symfony\Component\EventDispatcher\Attribute\EventListener;

#[EventListener(event: CustomEvent::class, method: 'onCustomEvent')]
#[EventListener(event: 'foo', priority: 42)]
#[EventListener(event: 'bar', method: 'onBarEvent')]
final class TaggedMultiListener
{
    public function onCustomEvent(CustomEvent $event): void
    {
    }

    public function onFoo(): void
    {
    }

    public function onBarEvent(): void
    {
    }
}
