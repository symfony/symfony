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
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesApiTransport;

class SesApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SesApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'),
                'ses+api://ACCESS_KEY@email.eu-west-1.amazonaws.com',
            ],
            [
                new SesApiTransport('ACCESS_KEY', 'SECRET_KEY', 'us-east-1'),
                'ses+api://ACCESS_KEY@email.us-east-1.amazonaws.com',
            ],
            [
                (new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com'),
                'ses+api://ACCESS_KEY@example.com',
            ],
            [
                (new SesApiTransport('ACCESS_KEY', 'SECRET_KEY'))->setHost('example.com')->setPort(99),
                'ses+api://ACCESS_KEY@example.com:99',
            ],
        ];
    }
}
