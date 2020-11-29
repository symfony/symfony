<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Amazon\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Amazon\AmazonTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

class AmazonTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn(): void
    {
        $factory = new AmazonTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://auth@default?region=eu-west-3'));

        $this->assertSame('sns://localhost?region=eu-west-3', (string) $transport);
    }

    public function testCreateWithoutCredentialDsn(): void
    {
        $factory = new AmazonTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://default?region=eu-west-3'));

        $this->assertSame('sns://localhost?region=eu-west-3', (string) $transport);
    }

    public function testDefaultRegionIsCorrectlySet(): void
    {
        $factory = new AmazonTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://default'));

        $this->assertSame('sns://localhost?region=us-east-1', (string) $transport);
    }

    public function testSupportsSnsScheme(): void
    {
        $factory = new AmazonTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('sns://default')));
        $this->assertFalse($factory->supports(Dsn::fromString('not-sns://default')));
    }

    public function testNonFreeMobileSchemeThrows(): void
    {
        $factory = new AmazonTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $unsupportedDsn = 'not-sns://localhost';
        $factory->create(Dsn::fromString($unsupportedDsn));
    }
}
