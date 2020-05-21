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
use Symfony\Component\Scheduler\Bag\MessengerBag;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MessengerBagTest extends TestCase
{
    public function testBagCanReturnNotifications(): void
    {
        $message = new FooMessage();

        $bag = new MessengerBag([$message], [$message], [$message]);

        static::assertSame('messenger', $bag->getName());

        static::assertArrayHasKey('before', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['before']);

        static::assertArrayHasKey('after', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['after']);

        static::assertArrayHasKey('failure', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['failure']);
    }
}

final class FooMessage
{
}
