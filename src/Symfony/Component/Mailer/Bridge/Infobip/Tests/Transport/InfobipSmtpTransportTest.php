<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Infobip\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipSmtpTransport;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class InfobipSmtpTransportTest extends TestCase
{
    public function testTrackingHeader()
    {
        $transport = new InfobipSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(InfobipSmtpTransport::class, 'addInfobipHeaders');

        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $enabled);
        $this->assertSame('X-Infobip-TrackOpens: true', $enabled->getHeaders()->get('X-Infobip-TrackOpens')->toString());
        $this->assertSame('X-Infobip-TrackClicks: true', $enabled->getHeaders()->get('X-Infobip-TrackClicks')->toString());
        $this->assertFalse($enabled->getHeaders()->has('X-Track'));

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $disabled);
        $this->assertSame('X-Infobip-TrackOpens: false', $disabled->getHeaders()->get('X-Infobip-TrackOpens')->toString());
        $this->assertSame('X-Infobip-TrackClicks: false', $disabled->getHeaders()->get('X-Infobip-TrackClicks')->toString());
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $transport = new InfobipSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(InfobipSmtpTransport::class, 'addInfobipHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(clicks: false));
        $method->invoke($transport, $email);

        $this->assertNull($email->getHeaders()->get('X-Infobip-TrackOpens'));
        $this->assertSame('X-Infobip-TrackClicks: false', $email->getHeaders()->get('X-Infobip-TrackClicks')->toString());
    }

    public function testExplicitInfobipTrackingHeaderWinsOverTrackingHeader()
    {
        $transport = new InfobipSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(InfobipSmtpTransport::class, 'addInfobipHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Infobip-TrackOpens', 'false');
        $email->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-Infobip-TrackOpens')));
        $this->assertSame('X-Infobip-TrackOpens: false', $email->getHeaders()->get('X-Infobip-TrackOpens')->toString());
        $this->assertSame('X-Infobip-TrackClicks: true', $email->getHeaders()->get('X-Infobip-TrackClicks')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }
}
