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
    public function testCreateWithDsn(): void
    {
        $factory = new DiscordTransportFactory();

        $host = 'testHost';
        $channel = 'testChannel';

        $transport = $factory->create(Dsn::fromString(sprintf('discord://%s@%s/?channel=%s', 'token', $host, $channel)));

        $this->assertSame(sprintf('discord://%s?channel=%s', $host, $channel), (string) $transport);
    }

    public function testCreateWithNoTokenThrowsMalformed(): void
    {
        $factory = new DiscordTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('discord://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsDiscordScheme(): void
    {
        $factory = new DiscordTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('discord://host/?channel=testChannel')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/?channel=testChannel')));
    }

    public function testNonDiscordSchemeThrows(): void
    {
        $factory = new DiscordTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://token@host/?channel=testChannel'));
    }
}
