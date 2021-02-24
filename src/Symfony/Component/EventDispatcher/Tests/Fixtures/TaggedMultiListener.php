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
