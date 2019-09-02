<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;

class PostmarkApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(PostmarkApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new PostmarkApiTransport('KEY'),
                'postmark+api://api.postmarkapp.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com'),
                'postmark+api://example.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'postmark+api://example.com:99',
            ],
        ];
    }
}
