<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class TwilioTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $dsn = 'twilio://accountSid:authToken@default?from=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('twilio://host.test?from=0611223344', (string) $transport);
    }

    public function testCreateWithNoFromThrowsMalformed()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'twilio://accountSid:authToken@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsTwilioScheme()
    {
        $factory = $this->createFactory();

        $dsn = 'twilio://accountSid:authToken@default?from=0611223344';
        $dsnUnsupported = 'twilioooo://accountSid:authToken@default?from=0611223344';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    public function testNonTwilioSchemeThrows()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $dsnUnsupported = 'twilioooo://accountSid:authToken@default?from=0611223344';
        $factory->create(Dsn::fromString($dsnUnsupported));
    }

    private function createFactory(): TwilioTransportFactory
    {
        return new TwilioTransportFactory();
    }
}
