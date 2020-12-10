<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class DiscordTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new DiscordTransportFactory();

        $host = 'testHost';
        $webhookId = 'testChannel';

        $transport = $factory->create(Dsn::fromString(sprintf('discord://%s@%s/?webhook_id=%s', 'token', $host, $webhookId)));

        $this->assertSame(sprintf('discord://%s?webhook_id=%s', $host, $webhookId), (string) $transport);
    }

    public function testCreateWithNoWebhookIdThrowsMalformed()
    {
        $factory = new DiscordTransportFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('discord://token@host'));
    }

    public function testCreateWithNoTokenThrowsMalformed()
    {
        $factory = new DiscordTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('discord://%s/?webhook_id=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsDiscordScheme()
    {
        $factory = new DiscordTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('discord://host/?webhook_id=testChannel')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/?webhook_id=testChannel')));
    }

    public function testNonDiscordSchemeThrows()
    {
        $factory = new DiscordTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://token@host/?webhook_id=testChannel'));
    }
}
