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

use Symfony\Component\EventDispatcher\Attribute\AsEventSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AsEventSubscriber([
    CustomEvent::class => 'onEvent',
])]
final class InvalidTaggedSubscriber implements EventSubscriberInterface
{
    public function onEvent(CustomEvent $event): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomEvent::class => 'onEvent',
        ];
    }
}
