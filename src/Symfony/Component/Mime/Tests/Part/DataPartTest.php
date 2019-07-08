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
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\DataPart;

class DataPartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new DataPart('content');
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('application', $p->getMediaType());
        $this->assertEquals('octet-stream', $p->getMediaSubType());

        $p = new DataPart('content', null, 'text/html');
        $this->assertEquals('text', $p->getMediaType());
        $this->assertEquals('html', $p->getMediaSubType());
    }

    public function testConstructorWithResource()
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);
        $p = new DataPart($f);
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        fclose($f);
    }

    public function testConstructorWithNonStringOrResource()
    {
        $this->expectException(\TypeError::class);
        new DataPart(new \stdClass());
    }

    public function testHeaders()
    {
        $p = new DataPart('content');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'application/octet-stream'),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment')
        ), $p->getPreparedHeaders());

        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.jpg', 'filename' => 'photo.jpg'])
        ), $p->getPreparedHeaders());
    }

    public function testAsInline()
    {
        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $p->asInline();
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'inline', ['name' => 'photo.jpg', 'filename' => 'photo.jpg'])
        ), $p->getPreparedHeaders());
    }

    public function testAsInlineWithCID()
    {
        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $p->asInline();
        $cid = $p->getContentId();
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'inline', ['name' => 'photo.jpg', 'filename' => 'photo.jpg']),
            new IdentificationHeader('Content-ID', $cid)
        ), $p->getPreparedHeaders());
    }

    public function testFromPath()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../Fixtures/mimetypes/test.gif');
        $content = file_get_contents($file);
        $this->assertEquals($content, $p->getBody());
        $this->assertEquals(base64_encode($content), $p->bodyToString());
        $this->assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('image', $p->getMediaType());
        $this->assertEquals('gif', $p->getMediaSubType());
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/gif', ['name' => 'test.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'test.gif', 'filename' => 'test.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testFromPathWithMeta()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../Fixtures/mimetypes/test.gif', 'photo.gif', 'image/jpeg');
        $content = file_get_contents($file);
        $this->assertEquals($content, $p->getBody());
        $this->assertEquals(base64_encode($content), $p->bodyToString());
        $this->assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('image', $p->getMediaType());
        $this->assertEquals('jpeg', $p->getMediaSubType());
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/jpeg', ['name' => 'photo.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.gif', 'filename' => 'photo.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testHasContentId()
    {
        $p = new DataPart('content');
        $this->assertFalse($p->hasContentId());
        $p->getContentId();
        $this->assertTrue($p->hasContentId());
    }

    public function testSerialize()
    {
        $r = fopen('php://memory', 'r+', false);
        fwrite($r, 'Text content');
        rewind($r);

        $p = new DataPart($r);
        $p->getHeaders()->addTextHeader('foo', 'bar');
        $expected = clone $p;
        $this->assertEquals($expected->toString(), unserialize(serialize($p))->toString());
    }
}
