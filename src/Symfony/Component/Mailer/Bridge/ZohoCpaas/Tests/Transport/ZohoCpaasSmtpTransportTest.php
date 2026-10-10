<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport\ZohoCpaasSmtpTransport;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class ZohoCpaasSmtpTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(ZohoCpaasSmtpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): iterable
    {
        yield [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD'),
            'smtp://smtp.zeptomail.com:587',
        ];

        yield [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', null, null, true),
            'smtps://smtp.zeptomail.com',
        ];

        yield [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', 'smtp.zeptomail.eu'),
            'smtp://smtp.zeptomail.eu:587',
        ];

        yield [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', 'smtp.zeptomail.eu', null, true),
            'smtps://smtp.zeptomail.eu',
        ];

        yield 'a port of your own' => [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', 'smtp.zeptomail.eu', 2525),
            'smtp://smtp.zeptomail.eu:2525',
        ];

        yield 'a port of your own, TLS from the start' => [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', null, 2465, true),
            'smtps://smtp.zeptomail.com:2465',
        ];

        yield 'port 465 alone is TLS from the start' => [
            new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD', null, 465),
            'smtps://smtp.zeptomail.com',
        ];
    }

    public function testTrackingHeader()
    {
        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(true, true));
        $this->addTrackingHeaders($enabled);

        $this->assertSame('X-TM-OPEN-TRACK: true', $enabled->getHeaders()->get('X-TM-OPEN-TRACK')->toString());
        $this->assertSame('X-TM-CLICK-TRACK: true', $enabled->getHeaders()->get('X-TM-CLICK-TRACK')->toString());
        $this->assertFalse($enabled->getHeaders()->has(TrackingHeader::NAME));

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(false, false));
        $this->addTrackingHeaders($disabled);

        $this->assertSame('X-TM-OPEN-TRACK: false', $disabled->getHeaders()->get('X-TM-OPEN-TRACK')->toString());
        $this->assertSame('X-TM-CLICK-TRACK: false', $disabled->getHeaders()->get('X-TM-CLICK-TRACK')->toString());
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(false));
        $this->addTrackingHeaders($email);

        $this->assertSame('X-TM-OPEN-TRACK: false', $email->getHeaders()->get('X-TM-OPEN-TRACK')->toString());
        $this->assertFalse($email->getHeaders()->has('X-TM-CLICK-TRACK'));
    }

    public function testExplicitZohoTrackingHeaderWinsOverTrackingHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('X-TM-OPEN-TRACK', 'false');
        $email->getHeaders()->add(new TrackingHeader(true, true));
        $this->addTrackingHeaders($email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-TM-OPEN-TRACK')));
        $this->assertSame('X-TM-OPEN-TRACK: false', $email->getHeaders()->get('X-TM-OPEN-TRACK')->toString());
        $this->assertSame('X-TM-CLICK-TRACK: true', $email->getHeaders()->get('X-TM-CLICK-TRACK')->toString());
        $this->assertFalse($email->getHeaders()->has(TrackingHeader::NAME));
    }

    public function testPlainTextTrackingHeaderIsResolved()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader(TrackingHeader::NAME, 'opens=false; clicks=true');
        $this->addTrackingHeaders($email);

        $this->assertSame('X-TM-OPEN-TRACK: false', $email->getHeaders()->get('X-TM-OPEN-TRACK')->toString());
        $this->assertSame('X-TM-CLICK-TRACK: true', $email->getHeaders()->get('X-TM-CLICK-TRACK')->toString());
        $this->assertFalse($email->getHeaders()->has(TrackingHeader::NAME));
    }

    private function addTrackingHeaders(Email $email): void
    {
        $method = new \ReflectionMethod(ZohoCpaasSmtpTransport::class, 'addTrackingHeaders');
        $method->invoke(new ZohoCpaasSmtpTransport('emailapikey', 'PASSWORD'), $email->getHeaders());
    }
}
