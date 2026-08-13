<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsProxima\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaTransport;
use Symfony\Component\Notifier\Bridge\SmsProxima\SmsProximaTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

class SmsProximaTransportFactoryTest extends TestCase
{
    public function testCreateWithValidDsn()
    {
        $factory = new SmsProximaTransportFactory();
        $transport = $factory->create(new Dsn('sms-proxima://my-token@default?from=BOUTIQUE'));

        $this->assertInstanceOf(SmsProximaTransport::class, $transport);
        $this->assertSame('sms-proxima://default?from=BOUTIQUE', (string) $transport);
    }

    public function testCreateThrowsOnUnsupportedScheme()
    {
        $this->expectException(UnsupportedSchemeException::class);

        $factory = new SmsProximaTransportFactory();
        $factory->create(new Dsn('other-scheme://token@default'));
    }
}
