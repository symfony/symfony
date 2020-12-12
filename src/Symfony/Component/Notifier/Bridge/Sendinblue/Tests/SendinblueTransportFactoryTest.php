<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sendinblue\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SendinblueTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->initFactory();

        $dsn = 'sendinblue://apiKey@default?sender=0611223344';
        $transport = $factory->create(Dsn::fromString($dsn));
        $transport->setHost('host.test');

        $this->assertSame('sendinblue://host.test?sender=0611223344', (string) $transport);
    }

    public function testCreateWithNoPhoneThrowsMalformed()
    {
        $factory = $this->initFactory();

        $this->expectException(IncompleteDsnException::class);

        $dsnIncomplete = 'sendinblue://apiKey@default';
        $factory->create(Dsn::fromString($dsnIncomplete));
    }

    public function testSupportsSendinblueScheme()
    {
        $factory = $this->initFactory();

        $dsn = 'sendinblue://apiKey@default?sender=0611223344';
        $dsnUnsupported = 'foobarmobile://apiKey@default?sender=0611223344';

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
        $this->assertFalse($factory->supports(Dsn::fromString($dsnUnsupported)));
    }

    private function initFactory(): SendinblueTransportFactory
    {
        return new SendinblueTransportFactory();
    }
}
