<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillHttpTransport;

class MandrillHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillHttpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MandrillHttpTransport('KEY'),
                'mandrill+https://mandrillapp.com',
            ],
            [
                (new MandrillHttpTransport('KEY'))->setHost('example.com'),
                'mandrill+https://example.com',
            ],
            [
                (new MandrillHttpTransport('KEY'))->setHost('example.com')->setPort(99),
                'mandrill+https://example.com:99',
            ],
        ];
    }
}
