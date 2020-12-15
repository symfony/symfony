<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OvhCloud\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class OvhCloudTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $dsn = 'ovhcloud://applicationKey:applicationSecret@default?consumer_key=consumerKey&service_name=serviceName';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('ovhcloud://host.test?consumer_key=consumerKey&service_name=serviceName', (string) $transport);
    }

    public function testCreateWithMissingOptionConsumerKeyThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('ovhcloud://applicationKey:applicationSecret@default?service_name=serviceName'));
    }

    public function testCreateWithMissingOptionServiceNameThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('ovhcloud://applicationKey:applicationSecret@default?consumeer_key=consumerKey'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('ovhcloud://applicationKey:applicationSecret@default?consumer_key=consumerKey&service_name=serviceName')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://applicationKey:applicationSecret@default?consumer_key=consumerKey&service_name=serviceName')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://applicationKey:applicationSecret@default?consumer_key=consumerKey&service_name=serviceName'));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "service_name" option
        $factory->create(Dsn::fromString('somethingElse://applicationKey:applicationSecret@default?consumer_key=consumerKey'));
    }

    private function createFactory(): OvhCloudTransportFactory
    {
        return new OvhCloudTransportFactory();
    }
}
