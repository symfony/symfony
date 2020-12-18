<?php

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class LinkedInTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('linkedin://accessToken:UserId@host.test'));

        $this->assertSame('linkedin://host.test', (string) $transport);
    }

    public function testCreateWithOnlyAccessTokenOrUserIdThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create(Dsn::fromString('linkedin://AccessTokenOrUserId@default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('linkedin://host/path')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/path')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://accessToken:UserId@default'));
    }

    private function createFactory(): LinkedInTransportFactory
    {
        return new LinkedInTransportFactory();
    }
}
