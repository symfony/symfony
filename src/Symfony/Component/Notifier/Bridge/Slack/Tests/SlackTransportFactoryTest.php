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
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SlackTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new SlackTransportFactory();

        $transport = $factory->create(Dsn::fromString('slack://testUser@testHost/?channel=testChannel'));

        $this->assertSame('slack://testHost?channel=testChannel', (string) $transport);
    }

    public function testCreateWithDeprecatedDsn()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Support for Slack webhook DSN has been dropped since 5.2 (maybe you haven\'t updated the DSN when upgrading from 5.1).');

        $factory = new SlackTransportFactory();
        $factory->create(Dsn::fromString('slack://default/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX'));
    }

    public function testCreateWithNoTokenThrowsMalformed()
    {
        $factory = new SlackTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('slack://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsScheme()
    {
        $factory = new SlackTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('slack://host/?channel=testChannel')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/?channel=testChannel')));
    }

    public function testNonSlackSchemeThrows()
    {
        $factory = new SlackTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://user:pwd@host/?channel=testChannel'));
    }
}
