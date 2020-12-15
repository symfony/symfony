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

        $factory->create(Dsn::fromString( 'somethingElse://token@default?from=Symfony'));
    }

    public function testCreateWithNoTokenThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('gatewayapi://host.test?from=Symfony'));
    }

    public function testCreateWithNoFromShouldThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('gatewayapi://token@host.test'));
    }

    private function createFactory(): GatewayApiTransportFactory
    {
        return new GatewayApiTransportFactory();
    }
}
