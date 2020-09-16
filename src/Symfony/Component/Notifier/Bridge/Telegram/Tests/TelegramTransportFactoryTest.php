<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class TelegramTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn(): void
    {
        $factory = new TelegramTransportFactory();

        $host = 'testHost';
        $channel = 'testChannel';

        $transport = $factory->create(Dsn::fromString(sprintf('telegram://%s@%s/?channel=%s', 'testUser:testPassword', $host, $channel)));

        $this->assertSame(sprintf('telegram://%s?channel=%s', $host, $channel), (string) $transport);
    }

    public function testCreateWithNoPasswordThrowsMalformed(): void
    {
        $factory = new TelegramTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('telegram://%s@%s/?channel=%s', 'simpleToken', 'testHost', 'testChannel')));
    }

    public function testCreateWithNoTokenThrowsMalformed(): void
    {
        $factory = new TelegramTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('telegram://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsTelegramScheme(): void
    {
        $factory = new TelegramTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('telegram://host/?channel=testChannel')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/?channel=testChannel')));
    }

    public function testNonTelegramSchemeThrows(): void
    {
        $factory = new TelegramTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://user:pwd@host/?channel=testChannel'));
    }
}
