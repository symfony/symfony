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
use Symfony\Component\Notifier\Transport\Dsn;

class AmazonSnsTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://auth@test.host?region=eu-west-3&profile=myProfile'));

        $this->assertSame('sns://test.host?region=eu-west-3', (string) $transport);
    }

    public function testCreateWithoutCredentialDsn()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://test.host?region=eu-west-3'));

        $this->assertSame('sns://test.host?region=eu-west-3', (string) $transport);
    }

    public function testDefaultRegionIsCorrectlySet()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://test.host'));

        $this->assertSame('sns://test.host?region=eu-west-1', (string) $transport);
    }

    public function testDsnWithRegionOption()
    {
        $factory = new AmazonSnsTransportFactory();
        $transport = $factory->create(Dsn::fromString('sns://test.host?region=eu-west-3'));

        $this->assertSame('sns://test.host?region=eu-west-3', (string) $transport);
    }

    public function testSupportsSnsScheme()
    {
        $factory = new AmazonSnsTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('sns://default')));
        $this->assertFalse($factory->supports(Dsn::fromString('not-sns://default')));
    }
}
