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
    public function testCreateWithDsn(): void
    {
        $factory = $this->initFactory();

        $dsn = 'freemobile://login:pass@default?phone=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('freemobile://host.test?phone=0611223344', (string) $transport);
    }

    public function testCreateWithNoPhoneThrowsMalformed(): void
    {
        $factory = $this->initFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'freemobile://login:pass@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsFreeMobileScheme(): void
    {
        $factory = $this->initFactory();

        $dsn = 'freemobile://login:pass@default?phone=0611223344';
        $dsnUnsupported = 'foobarmobile://login:pass@default?phone=0611223344';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testNonFreeMobileSchemeThrows(): void
    {
        $factory = $this->initFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'foobarmobile://login:pass@default?phone=0611223344';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }

    private function initFactory(): FreeMobileTransportFactory
    {
        return new FreeMobileTransportFactory();
    }
}
