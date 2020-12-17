<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class InfobipTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = new InfobipTransportFactory();

        $dsn = 'infobip://authtoken@default?from=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('infobip://host.test?from=0611223344', (string) $transport);
    }

    public function testCreateWithNoFromThrowsMalformed()
    {
        $factory = new InfobipTransportFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'infobip://authtoken@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsInfobipScheme()
    {
        $factory = new InfobipTransportFactory();

        $dsn = 'infobip://authtoken@default?from=0611223344';
        $dsnUnsupported = 'unsupported://authtoken@default?from=0611223344';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testNonInfobipSchemeThrows()
    {
        $factory = new InfobipTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'unsupported://authtoken@default?from=0611223344';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }
}
