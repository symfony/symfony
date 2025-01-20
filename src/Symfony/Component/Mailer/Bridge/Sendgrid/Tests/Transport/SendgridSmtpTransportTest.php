<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridSmtpTransport;

class SendgridSmtpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SendgridSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new SendgridSmtpTransport('KEY'),
                'smtps://smtp.sendgrid.net',
            ],
            [
                new SendgridSmtpTransport('KEY', null, null, 'eu'),
                'smtps://smtp.eu.sendgrid.net',
            ],
        ];
    }
}
