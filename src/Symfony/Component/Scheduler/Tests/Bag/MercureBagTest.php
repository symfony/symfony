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
use Symfony\Component\Mercure\Update;
use Symfony\Component\Scheduler\Bag\MercureBag;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MercureBagTest extends TestCase
{
    public function testBagCanReturnNotifications(): void
    {
        $update = new Update('test', 'test');

        $bag = new MercureBag([$update], [$update], [$update]);

        static::assertSame('mercure', $bag->getName());

        static::assertArrayHasKey('before', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['before']);

        static::assertArrayHasKey('after', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['after']);

        static::assertArrayHasKey('failure', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['failure']);
    }
}
