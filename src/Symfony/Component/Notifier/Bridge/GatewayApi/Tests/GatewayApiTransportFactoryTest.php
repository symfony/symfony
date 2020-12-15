<?php

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class GatewayApiTransportFactoryTest extends TestCase
{
    public function testSupportsGatewayApiScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('gatewayapi://token@host.test?from=Symfony')));
    }

    public function testUnSupportedGatewayShouldThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();
        $this->expectException(UnsupportedSchemeException::class);
        $dsn = 'wrongGateway://token@default?from=Symfony';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testCreateWithNoTokenThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();
        $this->expectException(IncompleteDsnException::class);
        $dsn = 'gatewayapi://default?from=Symfony';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testCreateWithNoFromShouldThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();
        $this->expectException(IncompleteDsnException::class);
        $dsn = 'gatewayapi://token@default';
        $factory->create(Dsn::fromString($dsn));
    }

    private function createFactory(): GatewayApiTransportFactory
    {
        return new GatewayApiTransportFactory();
    }
}
