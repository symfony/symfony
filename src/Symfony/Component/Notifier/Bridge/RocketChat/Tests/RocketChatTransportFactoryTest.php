<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class RocketChatTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new RocketChatTransportFactory();

        $accessToken = 'testAccessToken';
        $host = 'testHost';
        $channel = 'testChannel';

        $transport = $factory->create(Dsn::fromString(sprintf('rocketchat://%s@%s/?channel=%s', $accessToken, $host, $channel)));

        $this->assertSame(sprintf('rocketchat://%s?channel=%s', $host, $channel), (string) $transport);
    }

    public function testCreateWithNoTokenThrowsMalformed()
    {
        $factory = new RocketChatTransportFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('rocketchat://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = new RocketChatTransportFactory();

        $dsn = 'rocketchat://token@host/?channel=testChannel';
        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = new RocketChatTransportFactory();

        $dsnUnsupported = 'somethingElse://token@host/?channel=testChannel';
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = new RocketChatTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'somethingElse://token@host/?channel=testChannel';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }
}
