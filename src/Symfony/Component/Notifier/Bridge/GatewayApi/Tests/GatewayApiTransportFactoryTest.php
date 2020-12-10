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
        $factory = $this->initFactory();

        $dsn = 'gatewayapi://token@default?from=Symfony';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
    }

    public function testUnSupportedGatewayShouldThrowsUnsupportedSchemeException()
    {
        $factory = $this->initFactory();
        $this->expectException(UnsupportedSchemeException::class);
        $dsn = 'wrongGateway://token@default?from=Symfony';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testCreateWithNoTokenThrowsIncompleteDsnException()
    {
        $factory = $this->initFactory();
        $this->expectException(IncompleteDsnException::class);
        $dsn = 'gatewayapi://default?from=Symfony';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testCreateWithNoFromShouldThrowsIncompleteDsnException()
    {
        $factory = $this->initFactory();
        $this->expectException(IncompleteDsnException::class);
        $dsn = 'gatewayapi://token@default';
        $factory->create(Dsn::fromString($dsn));
    }

    private function initFactory(): GatewayApiTransportFactory
    {
        return new GatewayApiTransportFactory();
    }
}
