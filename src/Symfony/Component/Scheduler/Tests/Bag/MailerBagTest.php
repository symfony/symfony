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
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Scheduler\Bag\MailerBag;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MailerBagTest extends TestCase
{
    public function testBagCanReturnMails(): void
    {
        $mail = $this->createMock(RawMessage::class);

        $bag = new MailerBag([$mail], [$mail], [$mail]);

        static::assertSame('mailer', $bag->getName());

        static::assertArrayHasKey('before', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['before']);

        static::assertArrayHasKey('after', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['after']);

        static::assertArrayHasKey('failure', $bag->getContent());
        static::assertNotEmpty($bag->getContent()['failure']);
    }
}
