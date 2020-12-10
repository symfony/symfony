<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

class AmazonSnsTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://auth@default?region=eu-west-3&profile=myProfile'));
        $transport->setHost('example.com');

        $this->assertSame('sns://example.com?region=eu-west-3', (string) $transport);
    }

    public function testCreateWithoutCredentialDsn()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://default?region=eu-west-3'));
        $transport->setHost('example.com');

        $this->assertSame('sns://example.com?region=eu-west-3', (string) $transport);
    }

    public function testDefaultRegionIsCorrectlySet()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://default'));
        $transport->setHost('example.com');

        $this->assertSame('sns://example.com?region=eu-west-1', (string) $transport);
    }

    public function testDsnWithRegionOption()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://default?region=eu-west-3'));
        $transport->setHost('example.com');

        $this->assertSame('sns://example.com?region=eu-west-3', (string) $transport);
    }

    public function testSupportsSnsScheme()
    {
        $factory = new AmazonSnsTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('sns://default')));
        $this->assertFalse($factory->supports(Dsn::fromString('not-sns://default')));
    }

    public function testNonFreeMobileSchemeThrows()
    {
        $factory = new AmazonSnsTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $unsupportedDsn = 'not-sns://example.com';
        $factory->create(Dsn::fromString($unsupportedDsn));
    }
}
