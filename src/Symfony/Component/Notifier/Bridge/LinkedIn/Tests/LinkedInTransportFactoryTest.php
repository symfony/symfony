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
        $factory = new LinkedInTransportFactory();

        $dsn = 'linkedin://login:pass@default';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('testHost');

        $this->assertSame('linkedin://testHost', (string) $transport);
    }

    public function testSupportsLinkedinScheme()
    {
        $factory = new LinkedInTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('linkedin://host/path')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/path')));
    }

    public function testNonLinkedinSchemeThrows()
    {
        $factory = new LinkedInTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsn = 'foo://login:pass@default';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testIncompleteDsnMissingUserThrows()
    {
        $factory = new LinkedInTransportFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('somethingElse://host/path'));
    }
}
