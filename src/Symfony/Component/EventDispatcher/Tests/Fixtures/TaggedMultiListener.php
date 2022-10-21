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

#[AsEventListener(event: CustomEvent::class, method: 'onCustomEvent')]
#[AsEventListener(event: 'foo', priority: 42)]
#[AsEventListener(event: 'bar', method: 'onBarEvent')]
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

    #[AsEventListener(event: 'baz')]
    public function onBazEvent(): void
    {
    }
}
