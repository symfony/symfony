<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Nexmo\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class NexmoTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $dsn = 'nexmo://apiKey:apiSecret@default?from=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('nexmo://host.test?from=0611223344', (string) $transport);
    }

    public function testCreateWithMissingOptionFromThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'nexmo://apiKey:apiSecret@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $dsn = 'nexmo://apiKey:apiSecret@default?from=0611223344';
        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $dsnUnsupported = 'nexmoo://apiKey:apiSecret@default?from=0611223344';
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'nexmoo://apiKey:apiSecret@default?from=0611223344';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "from" option
        $factory->create(Dsn::fromString('nexmoo://apiKey:apiSecret@default'));
    }

    private function createFactory(): NexmoTransportFactory
    {
        return new NexmoTransportFactory();
    }
}
