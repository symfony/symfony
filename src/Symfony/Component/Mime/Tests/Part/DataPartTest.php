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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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

    public function testFromPathWithNotAFile()
    {
        $this->expectException(InvalidArgumentException::class);
        DataPart::fromPath(__DIR__.'/../Fixtures/mimetypes/');
    }

    public function testFromPathWithUrl()
    {
        if (!\in_array('http', stream_get_wrappers(), true)) {
            $this->markTestSkipped('"http" stream wrapper is not enabled.');
        }

        $finder = new PhpExecutableFinder();
        $process = new Process(array_merge([$finder->find(false)], $finder->findArguments(), ['-dopcache.enable=0', '-dvariables_order=EGPCS', '-S', 'localhost:8856']));
        $process->setWorkingDirectory(__DIR__.'/../Fixtures/web');
        $process->start();

        try {
            do {
                usleep(50000);
            } while (!@fopen('http://localhost:8856', 'r'));
            $p = DataPart::fromPath($file = 'http://localhost:8856/logo_symfony_header.png');
            $content = file_get_contents($file);
            $this->assertEquals($content, $p->getBody());
            $maxLineLength = 76;
            $this->assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr($p->bodyToString(), 0, $maxLineLength));
            $this->assertEquals(substr(base64_encode($content), 0, $maxLineLength), substr(implode('', iterator_to_array($p->bodyToIterable())), 0, $maxLineLength));
            $this->assertEquals('image', $p->getMediaType());
            $this->assertEquals('png', $p->getMediaSubType());
            $this->assertEquals(new Headers(
                new ParameterizedHeader('Content-Type', 'image/png', ['name' => 'logo_symfony_header.png']),
                new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
                new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'logo_symfony_header.png', 'filename' => 'logo_symfony_header.png'])
            ), $p->getPreparedHeaders());
        } finally {
            $process->stop();
        }
    }

    public function testHasContentId()
    {
        $p = new DataPart('content');
        $this->assertFalse($p->hasContentId());
        $p->getContentId();
        $this->assertTrue($p->hasContentId());
    }

    public function testSetContentId()
    {
        $p = new DataPart('content');
        $p->setContentId('test@test');
        $this->assertTrue($p->hasContentId());
        $this->assertSame('test@test', $p->getContentId());
    }

    public function testSetContentIdInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $p = new DataPart('content');
        $p->setContentId('test');
    }

    public function testGetFilename()
    {
        $p = new DataPart('content', null);
        self::assertNull($p->getFilename());

        $p = new DataPart('content', 'filename');
        self::assertSame('filename', $p->getFilename());
    }

    public function testGetContentType()
    {
        $p = new DataPart('content');
        self::assertSame('application/octet-stream', $p->getContentType());

        $p = new DataPart('content', null, 'application/pdf');
        self::assertSame('application/pdf', $p->getContentType());
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
