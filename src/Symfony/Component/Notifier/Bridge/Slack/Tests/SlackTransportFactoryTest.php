<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SlackTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn(): void
    {
        $factory = new SlackTransportFactory();

        $host = 'testHost';
        $channel = 'testChannel';
        $transport = $factory->create(Dsn::fromString(sprintf('slack://testUser@%s/?channel=%s', $host, $channel)));

        $this->assertSame(sprintf('slack://%s?channel=%s', $host, $channel), (string) $transport);
    }

    public function testCreateWithNoTokenThrowsMalformed(): void
    {
        $factory = new SlackTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('slack://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsSlackScheme(): void
    {
        $factory = new SlackTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('slack://host/?channel=testChannel')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/?channel=testChannel')));
    }

    public function testNonSlackSchemeThrows(): void
    {
        $factory = new SlackTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://user:pwd@host/?channel=testChannel'));
    }
}
