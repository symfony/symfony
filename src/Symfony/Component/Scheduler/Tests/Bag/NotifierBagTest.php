<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Bag;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Scheduler\Bag\NotifierBag;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NotifierBagTest extends TestCase
{
    public function testBagCanReturnNotifications(): void
    {
        $notification = $this->createMock(Notification::class);

        $bag = new NotifierBag([$notification], [$notification], [$notification]);

        static::assertSame('notifier', $bag->getName());

        static::assertArrayHasKey('before', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['before']);

        static::assertArrayHasKey('after', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['after']);

        static::assertArrayHasKey('failure', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['failure']);
    }
}
