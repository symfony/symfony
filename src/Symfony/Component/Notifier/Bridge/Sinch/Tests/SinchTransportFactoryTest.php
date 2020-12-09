<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sinch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SinchTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $dsn = 'sinch://accountSid:authToken@default?from=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('sinch://host.test?from=0611223344', (string) $transport);
    }

    public function testCreateWithMissingOptionFromThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'sinch://accountSid:authToken@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $dsn = 'sinch://accountSid:authToken@default?from=0611223344';
        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $dsnUnsupported = 'sinnnnch://accountSid:authToken@default?from=0611223344';
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'sinnnnch://accountSid:authToken@default?from=0611223344';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "from" option
        $factory->create(Dsn::fromString('sinnnnch://accountSid:authToken@default'));
    }

    private function createFactory(): SinchTransportFactory
    {
        return new SinchTransportFactory();
    }
}
