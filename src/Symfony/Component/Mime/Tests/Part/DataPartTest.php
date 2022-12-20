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
use Symfony\Component\Mime\Exception\InvalidArgumentException;
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
        self::assertEquals('content', $p->getBody());
        self::assertEquals(base64_encode('content'), $p->bodyToString());
        self::assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        self::assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals('application', $p->getMediaType());
        self::assertEquals('octet-stream', $p->getMediaSubType());

        $p = new DataPart('content', null, 'text/html');
        self::assertEquals('text', $p->getMediaType());
        self::assertEquals('html', $p->getMediaSubType());
    }

    public function testConstructorWithResource()
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);
        $p = new DataPart($f);
        self::assertEquals('content', $p->getBody());
        self::assertEquals(base64_encode('content'), $p->bodyToString());
        self::assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        fclose($f);
    }

    public function testConstructorWithNonStringOrResource()
    {
        self::expectException(\TypeError::class);
        new DataPart(new \stdClass());
    }

    public function testHeaders()
    {
        $p = new DataPart('content');
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'application/octet-stream'),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment')
        ), $p->getPreparedHeaders());

        $p = new DataPart('content', 'photo.jpg', 'text/html');
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/html', ['name' => 'photo.jpg']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.jpg', 'filename' => 'photo.jpg'])
        ), $p->getPreparedHeaders());
    }

    public function testAsInline()
    {
        $p = new DataPart('content', 'photo.jpg', 'text/html');
        $p->asInline();
        self::assertEquals(new Headers(
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
        self::assertEquals(new Headers(
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
        self::assertEquals($content, $p->getBody());
        self::assertEquals(base64_encode($content), $p->bodyToString());
        self::assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals('image', $p->getMediaType());
        self::assertEquals('gif', $p->getMediaSubType());
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/gif', ['name' => 'test.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'test.gif', 'filename' => 'test.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testFromPathWithMeta()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../Fixtures/mimetypes/test.gif', 'photo.gif', 'image/jpeg');
        $content = file_get_contents($file);
        self::assertEquals($content, $p->getBody());
        self::assertEquals(base64_encode($content), $p->bodyToString());
        self::assertEquals(base64_encode($content), implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals('image', $p->getMediaType());
        self::assertEquals('jpeg', $p->getMediaSubType());
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/jpeg', ['name' => 'photo.gif']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'photo.gif', 'filename' => 'photo.gif'])
        ), $p->getPreparedHeaders());
    }

    public function testFromPathWithNotAFile()
    {
        self::expectException(InvalidArgumentException::class);
        DataPart::fromPath(__DIR__.'/../Fixtures/mimetypes/');
    }

    /**
     * @group network
     */
    public function testFromPathWithUrl()
    {
        if (!\in_array('https', stream_get_wrappers())) {
            self::markTestSkipped('"https" stream wrapper is not enabled.');
        }

        $p = DataPart::fromPath($file = 'https://symfony.com/images/common/logo/logo_symfony_header.png');
        $content = file_get_contents($file);
        self::assertEquals($content, $p->getBody());
        $maxLineLength = 76;
        self::assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr($p->bodyToString(), 0, $maxLineLength));
        self::assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr(implode('', iterator_to_array($p->bodyToIterable())), 0, $maxLineLength));
        self::assertEquals('image', $p->getMediaType());
        self::assertEquals('png', $p->getMediaSubType());
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'image/png', ['name' => 'logo_symfony_header.png']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'logo_symfony_header.png', 'filename' => 'logo_symfony_header.png'])
        ), $p->getPreparedHeaders());
    }

    public function testHasContentId()
    {
        $p = new DataPart('content');
        self::assertFalse($p->hasContentId());
        $p->getContentId();
        self::assertTrue($p->hasContentId());
    }

    public function testSerialize()
    {
        $r = fopen('php://memory', 'r+', false);
        fwrite($r, 'Text content');
        rewind($r);

        $p = new DataPart($r);
        $p->getHeaders()->addTextHeader('foo', 'bar');
        $expected = clone $p;
        self::assertEquals($expected->toString(), unserialize(serialize($p))->toString());
    }
}
