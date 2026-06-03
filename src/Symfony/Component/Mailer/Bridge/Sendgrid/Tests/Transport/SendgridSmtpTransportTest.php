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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendgrid\Header\SuppressionGroupHeader;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridSmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendgridSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
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
            [
                new SendgridSmtpTransport('KEY', null, null, 'global'),
                'smtps://smtp.sendgrid.net',
            ],
        ];
    }

    public function testSuppressionGroupHeader()
    {
        $email = (new Email())->subject('Hello!')
            ->to(new Address('kevin@symfony.com', 'Kevin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');
        $email->getHeaders()->add(new SuppressionGroupHeader(1, [1, 2, 3, 4, 5]));

        $transport = new SendgridSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridSmtpTransport::class, 'addSendgridHeaders');
        $method->invoke($transport, $email);

        $this->assertFalse($email->getHeaders()->has('X-Sendgrid-SuppressionGroup'));
        $this->assertTrue($email->getHeaders()->has('X-SMTPAPI'));

        $json = $email->getHeaders()->get('X-SMTPAPI')->getBodyAsString();
        $payload = json_decode($json, true);

        $this->assertArrayHasKey('asm', $payload);
        $this->assertArrayHasKey('group_id', $payload['asm']);
        $this->assertArrayHasKey('groups_to_display', $payload['asm']);
        $this->assertCount(5, $payload['asm']['groups_to_display']);
        $this->assertSame([1, 2, 3, 4, 5], $payload['asm']['groups_to_display']);
    }
}
