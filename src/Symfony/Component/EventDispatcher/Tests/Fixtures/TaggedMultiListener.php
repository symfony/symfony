<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Fixtures;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(events: CustomEvent::class, method: 'onCustomEvent')]
#[AsEventListener(events: 'foo', priority: 42)]
#[AsEventListener(events: 'bar', method: 'onBarEvent')]
#[AsEventListener(events: [CustomEvent::class, 'foo', 'bar'], method: 'onMultipleEvents', priority: 10)]
#[AsEventListener(events: [CustomEvent::class, BizEvent::class])]
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

    #[AsEventListener(events: 'baz')]
    public function onBazEvent(): void
    {
    }

    public function onMultipleEvents(): void
    {
    }

    #[AsEventListener]
    public function onMultipleEventsWithSomeInvalids(BizEvent|CustomEvent|string|int $event): void
    {
    }

    public function __invoke()
    {
    }
}
