<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class FreeMobileTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $dsn = 'freemobile://login:pass@default?phone=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('freemobile://host.test?phone=0611223344', (string) $transport);
    }

    public function testCreateWithNoPhoneThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('freemobile://login:pass@default'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('freemobile://login:pass@default?phone=0611223344')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://login:pass@default?phone=0611223344')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://login:pass@default?phone=0611223344'));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "phone" option
        $factory->create(Dsn::fromString('somethingElse://login:pass@default'));
    }

    private function createFactory(): FreeMobileTransportFactory
    {
        return new FreeMobileTransportFactory();
    }
}
