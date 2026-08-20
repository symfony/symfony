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
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillSmtpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class MandrillSmtpTransportTest extends TestCase
{
    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset,user'));
        $email->getHeaders()->add(new TagHeader('another'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));

        $transport = new MandrillSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MandrillSmtpTransport::class, 'addMandrillHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(3, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
        $this->assertSame('X-MC-Tags: password-reset,user,another', $email->getHeaders()->get('X-MC-Tags')->toString());
        $this->assertSame('X-MC-Metadata: '.json_encode(['Color' => 'blue', 'Client-ID' => '12345']), $email->getHeaders()->get('X-MC-Metadata')->toString());
    }

    public function testTrackingHeader()
    {
        $transport = new MandrillSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MandrillSmtpTransport::class, 'addMandrillHeaders');

        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $enabled);
        $this->assertSame('X-MC-Track: opens,clicks', $enabled->getHeaders()->get('X-MC-Track')->toString());

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $disabled);
        $this->assertSame('X-MC-Track: none', $disabled->getHeaders()->get('X-MC-Track')->toString());
    }

    public function testTrackingHeaderOnlyEnablesExplicitAspects()
    {
        $transport = new MandrillSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MandrillSmtpTransport::class, 'addMandrillHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: true));
        $method->invoke($transport, $email);

        $this->assertSame('X-MC-Track: opens', $email->getHeaders()->get('X-MC-Track')->toString());
    }

    public function testExplicitMandrillTrackingHeaderWinsOverTrackingHeader()
    {
        $transport = new MandrillSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MandrillSmtpTransport::class, 'addMandrillHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-MC-Track', 'opens,clicks_htmlonly');
        $email->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-MC-Track')));
        $this->assertSame('X-MC-Track: opens,clicks_htmlonly', $email->getHeaders()->get('X-MC-Track')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }

    public function testTrackingHeaderWithBothAspectsUnsetKeepsTheAccountDefaults()
    {
        $transport = new MandrillSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MandrillSmtpTransport::class, 'addMandrillHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader());
        $method->invoke($transport, $email);

        $this->assertFalse($email->getHeaders()->has('X-MC-Track'));
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }
}
