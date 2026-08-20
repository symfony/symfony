<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetSmtpTransport;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class MailjetSmtpTransportTest extends TestCase
{
    public function testTrackingHeader()
    {
        $transport = new MailjetSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailjetSmtpTransport::class, 'addMailjetHeaders');

        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $enabled);
        $this->assertSame('X-Mailjet-TrackOpen: 1', $enabled->getHeaders()->get('X-Mailjet-TrackOpen')->toString());
        $this->assertSame('X-Mailjet-TrackClick: 1', $enabled->getHeaders()->get('X-Mailjet-TrackClick')->toString());
        $this->assertFalse($enabled->getHeaders()->has('X-Track'));

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $disabled);
        $this->assertSame('X-Mailjet-TrackOpen: 0', $disabled->getHeaders()->get('X-Mailjet-TrackOpen')->toString());
        $this->assertSame('X-Mailjet-TrackClick: 0', $disabled->getHeaders()->get('X-Mailjet-TrackClick')->toString());
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $transport = new MailjetSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailjetSmtpTransport::class, 'addMailjetHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: false));
        $method->invoke($transport, $email);

        $this->assertSame('X-Mailjet-TrackOpen: 0', $email->getHeaders()->get('X-Mailjet-TrackOpen')->toString());
        $this->assertNull($email->getHeaders()->get('X-Mailjet-TrackClick'));
    }

    public function testExplicitMailjetTrackingHeaderWinsOverTrackingHeader()
    {
        $transport = new MailjetSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailjetSmtpTransport::class, 'addMailjetHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Mailjet-TrackClick', '0');
        $email->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-Mailjet-TrackClick')));
        $this->assertSame('X-Mailjet-TrackClick: 0', $email->getHeaders()->get('X-Mailjet-TrackClick')->toString());
        $this->assertSame('X-Mailjet-TrackOpen: 1', $email->getHeaders()->get('X-Mailjet-TrackOpen')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }
}
