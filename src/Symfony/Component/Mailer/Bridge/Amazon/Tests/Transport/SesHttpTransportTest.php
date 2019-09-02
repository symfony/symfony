<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesHttpTransport;

class SesHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesHttpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'),
                'ses+https://ACCESS_KEY@email.eu-west-1.amazonaws.com',
            ],
            [
                new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY', 'us-east-1'),
                'ses+https://ACCESS_KEY@email.us-east-1.amazonaws.com',
            ],
            [
                (new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com'),
                'ses+https://ACCESS_KEY@example.com',
            ],
            [
                (new SesHttpTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com')->setPort(99),
                'ses+https://ACCESS_KEY@example.com:99',
            ],
        ];
    }
}
