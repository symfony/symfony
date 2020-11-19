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

        $dsn = 'sns://auth@default?region=eu-west-3';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('sns.host');

        $this->assertSame('sns://sns.host?region=eu-west-3', (string)$transport);
    }

    public function testDefaultRegionIsCorrectlySet(): void
    {
        $factory = new AmazonTransportFactory();

        $dsn = 'sns://auth@default';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('sns.host');

        $this->assertSame('sns://sns.host?region=eu-west-1', (string)$transport);
    }

    public function testSupportsSnsScheme(): void
    {
        $factory = new AmazonTransportFactory();

        $dsn = 'sns://auth@default';
        $unsupportedDsn = 'notsns://auth@default';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
        $this->assertFalse($factory->supports(Dsn::fromString($unsupportedDsn)));
    }

    public function testNonFreeMobileSchemeThrows(): void
    {
        $factory = new AmazonTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $unsupportedDsn = 'notsns://auth@default';
        $factory->create(Dsn::fromString($unsupportedDsn));
    }
}
