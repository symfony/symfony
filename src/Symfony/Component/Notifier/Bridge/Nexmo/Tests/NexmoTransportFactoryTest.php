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

        $transport = $factory->create(Dsn::fromString('nexmo://apiKey:apiSecret@host.test?from=0611223344'));

        $this->assertSame('nexmo://host.test?from=0611223344', (string) $transport);
    }

    public function testCreateWithMissingOptionFromThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('nexmo://apiKey:apiSecret@default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('nexmo://apiKey:apiSecret@default?from=0611223344')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('nexmoo://apiKey:apiSecret@default?from=0611223344')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://apiKey:apiSecret@default?from=0611223344'));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "from" option
        $factory->create(Dsn::fromString('somethingElse://apiKey:apiSecret@default'));
    }

    private function createFactory(): NexmoTransportFactory
    {
        return new NexmoTransportFactory();
    }
}
