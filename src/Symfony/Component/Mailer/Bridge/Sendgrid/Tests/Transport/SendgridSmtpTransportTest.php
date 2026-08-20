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
use Symfony\Component\Mailer\Header\TrackingHeader;
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

    public function testTrackingHeader()
    {
        $transport = new SendgridSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridSmtpTransport::class, 'addSendgridHeaders');

        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $enabled);
        $this->assertFalse($enabled->getHeaders()->has('X-Track'));
        $payload = json_decode($enabled->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true);
        $this->assertSame(1, $payload['filters']['opentrack']['settings']['enable']);
        $this->assertSame(1, $payload['filters']['clicktrack']['settings']['enable']);
        $this->assertTrue($payload['filters']['clicktrack']['settings']['enable_text']);

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $disabled);
        $payload = json_decode($disabled->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true);
        $this->assertSame(0, $payload['filters']['opentrack']['settings']['enable']);
        $this->assertSame(0, $payload['filters']['clicktrack']['settings']['enable']);
        $this->assertFalse($payload['filters']['clicktrack']['settings']['enable_text']);
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $transport = new SendgridSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridSmtpTransport::class, 'addSendgridHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(clicks: false));
        $method->invoke($transport, $email);

        $payload = json_decode($email->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true);
        $this->assertArrayNotHasKey('opentrack', $payload['filters']);
        $this->assertSame(0, $payload['filters']['clicktrack']['settings']['enable']);
    }

    public function testTrackingHeaderComposesWithSuppressionGroupHeader()
    {
        $transport = new SendgridSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridSmtpTransport::class, 'addSendgridHeaders');

        $email = new Email();
        $email->getHeaders()->add(new SuppressionGroupHeader(1, [1, 2]));
        $email->getHeaders()->add(new TrackingHeader(opens: false));
        $method->invoke($transport, $email);

        $payload = json_decode($email->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true);
        $this->assertSame(1, $payload['asm']['group_id']);
        $this->assertSame(0, $payload['filters']['opentrack']['settings']['enable']);
    }

    public function testExplicitSmtpApiHeaderWinsOverTrackingHeader()
    {
        $transport = new SendgridSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridSmtpTransport::class, 'addSendgridHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-SMTPAPI', '{"filters":{"opentrack":{"settings":{"enable":0}}}}');
        $email->getHeaders()->add(new TrackingHeader(opens: true));
        $method->invoke($transport, $email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-SMTPAPI')));
        $payload = json_decode($email->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true);
        $this->assertSame(0, $payload['filters']['opentrack']['settings']['enable']);
    }
}
