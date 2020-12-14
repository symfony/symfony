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

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $accessToken = 'testAccessToken';
        $host = 'testHost';
        $channel = 'testChannel';

        $transport = $factory->create(Dsn::fromString(sprintf('rocketchat://%s@%s/?channel=%s', $accessToken, $host, $channel)));

        $this->assertSame(sprintf('rocketchat://%s?channel=%s', $host, $channel), (string) $transport);
    }

    public function testCreateWithNoTokenThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString(sprintf('rocketchat://%s/?channel=%s', 'testHost', 'testChannel')));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('rocketchat://token@host/?channel=testChannel')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://token@host/?channel=testChannel')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://token@host/?channel=testChannel'));
    }

    private function createFactory(): RocketChatTransportFactory
    {
        return new RocketChatTransportFactory();
    }
}
