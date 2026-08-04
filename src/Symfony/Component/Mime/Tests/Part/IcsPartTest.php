<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\IcsPart;

class IcsPartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new IcsPart('content');
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('text', $p->getMediaType());
        $this->assertEquals('calendar', $p->getMediaSubtype());
        $this->assertEquals('inline', $p->getDisposition());

        $p = new IcsPart('content', 'calendar.ics', 'text/calendar');
        $this->assertEquals('text', $p->getMediaType());
        $this->assertEquals('calendar', $p->getMediaSubtype());
    }

    public function testHeaders()
    {
        $p = new IcsPart('content');
        $headers = $p->getPreparedHeaders();
        $this->assertEquals('text/calendar', $headers->get('Content-Type')->getBodyAsString());
        $this->assertEquals('base64', $headers->get('Content-Transfer-Encoding')->getBodyAsString());
        $this->assertEquals('inline', $headers->get('Content-Disposition')->getBodyAsString());
        $this->assertSame('name=invite.ics', $headers->get('Content-Disposition')->getParameter('name'));
        $this->assertSame('filename=invite.ics', $headers->get('Content-Disposition')->getParameter('filename'));
    }

    public function testHeadersWithCustomFilename()
    {
        $p = new IcsPart('content', 'mycalendar.ics');
        $headers = $p->getPreparedHeaders();
        $this->assertEquals('text/calendar', $headers->get('Content-Type')->getBodyAsString());
        $this->assertSame('name=mycalendar.ics', $headers->get('Content-Disposition')->getParameter('name'));
        $this->assertSame('filename=mycalendar.ics', $headers->get('Content-Disposition')->getParameter('filename'));
    }

    public function testAsInlineWithFilename()
    {
        $p = new IcsPart('content', 'event.ics');
        $p->asInline();
        $headers = $p->getPreparedHeaders();
        $this->assertEquals('inline', $headers->get('Content-Disposition')->getBodyAsString());
        $this->assertSame('name=event.ics', $headers->get('Content-Disposition')->getParameter('name'));
        $this->assertSame('filename=event.ics', $headers->get('Content-Disposition')->getParameter('filename'));
    }

    public function testAsInlineWithCID()
    {
        $p = new IcsPart('content', 'event.ics');
        $p->asInline();
        $cid = $p->getContentId();
        $headers = $p->getPreparedHeaders();
        $this->assertEquals($cid, $headers->get('Content-ID')->getBodyAsString());
    }

    public function testFindMethod()
    {
        $p = new IcsPart('BEGIN:VCALENDAR
METHOD:PUBLISH
END:VCALENDAR');
        $this->assertSame('PUBLISH', $p->findMethod($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nEND:VCALENDAR');
        $this->assertSame('REQUEST', $p->findMethod($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nEND:VCALENDAR');
        $this->assertNull($p->findMethod($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nMETHOD:INVALID\r\nEND:VCALENDAR');
        $this->expectException(\LogicException::class);
        $p->findMethod($p->getBody());
    }

    public function testFindComponents()
    {
        $p = new IcsPart('BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VEVENT\r\nEND:VCALENDAR');
        $this->assertSame(['vcalendar', 'vevent'], $p->findComponents($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nBEGIN:VTODO\r\nEND:VTODO\r\nEND:VCALENDAR');
        $this->assertSame(['vcalendar', 'vtodo'], $p->findComponents($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nBEGIN:VFREEBUSY\r\nEND:VFREEBUSY\r\nEND:VCALENDAR');
        $this->assertSame(['vcalendar', 'vfreebusy'], $p->findComponents($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nEND:VCALENDAR');
        $this->assertSame(['vcalendar'], $p->findComponents($p->getBody()));

        $p = new IcsPart('BEGIN:VCALENDAR\r\nBEGIN:VTODO\r\nBEGIN:VEVENT\r\nEND:VEVENT\r\nEND:VTODO\r\nEND:VCALENDAR');
        $this->expectException(\LogicException::class);
        $p->findComponents($p->getBody());
    }

    public function testGetPreparedHeadersWithMethod()
    {
        $icsContent = "BEGIN:VCALENDAR\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nEND:VEVENT\r\nEND:VCALENDAR";
        $p = new IcsPart($icsContent, 'meeting.ics');
        $headers = $p->getPreparedHeaders();
        $this->assertStringContainsString('text/calendar; name=meeting.ics; method=publish', $headers->get('Content-Type')->getBodyAsString());
        $this->assertStringContainsString('component=vcalendar; component=vevent', $headers->get('Content-Type')->getBodyAsString());
    }

    public function testGetPreparedHeadersWithMultipleComponents()
    {
        $icsContent = "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\nBEGIN:VFREEBUSY\r\nEND:VFREEBUSY\r\nBEGIN:VJOURNAL\r\nEND:VJOURNAL\r\nEND:VCALENDAR";
        $p = new IcsPart($icsContent, 'event.ics');
        $headers = $p->getPreparedHeaders();
        $this->assertStringContainsString('text/calendar; name=event.ics; method=request', $headers->get('Content-Type')->getBodyAsString());
        $this->assertStringContainsString('component=vcalendar; component=vfreebusy; component=vjournal', $headers->get('Content-Type')->getBodyAsString());
    }

    public function testGetPreparedHeadersWithVTimezoneOnly()
    {
        $icsContent = "BEGIN:VCALENDAR\r\nBEGIN:VTIMEZONE\r\nEND:VTIMEZONE\r\nEND:VCALENDAR";
        $p = new IcsPart($icsContent, 'timezone.ics');
        $headers = $p->getPreparedHeaders();
        $this->assertSame('text/calendar; name=timezone.ics; component=vtimezone', $headers->get('Content-Type')->getBodyAsString());
    }

    public function testFindMethodInvalidException()
    {
        $p = new IcsPart('BEGIN:VCALENDAR\r\nMETHOD:INVALID_METHOD\r\nEND:VCALENDAR');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Propriété METHOD absente ou invalide dans le contenu ICS.');
        $p->findMethod($p->getBody());
    }

    public function testFindComponentsMultipleComponentsException()
    {
        $p = new IcsPart('BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nBEGIN:VTODO\r\nEND:VTODO\r\nEND:VEVENT\r\nEND:VCALENDAR');
        $this->expectException(\LogicException::class);
        $p->findComponents($p->getBody());
    }
}
