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
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('slack://xoxb-TestUser@host.test/?channel=testChannel'));

        $this->assertSame('slack://host.test?channel=testChannel', (string) $transport);
    }

    public function testCreateWithDsnWithoutPath()
    {
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('slack://xoxb-TestUser@host.test?channel=testChannel'));

        $this->assertSame('slack://host.test?channel=testChannel', (string) $transport);
    }

    public function testCreateWithDeprecatedDsn()
    {
        $factory = $this->createFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Support for Slack webhook DSN has been dropped since 5.2 (maybe you haven\'t updated the DSN when upgrading from 5.1).');

        $factory->create(Dsn::fromString('slack://default/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX'));
    }

    public function testCreateWithNoTokenThrowsInclompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString('slack://host.test?channel=testChannel'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('slack://host?channel=testChannel')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host?channel=testChannel')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://host?channel=testChannel'));
    }

    private function createFactory(): SlackTransportFactory
    {
        return new SlackTransportFactory();
    }
}
