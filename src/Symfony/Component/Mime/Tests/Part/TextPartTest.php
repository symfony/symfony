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
use Symfony\Component\Mime\Encoder\ContentEncoderInterface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\TextPart;

class TextPartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new TextPart('content');
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals('content', $p->bodyToString());
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('text', $p->getMediaType());
        $this->assertEquals('plain', $p->getMediaSubType());

        $p = new TextPart('content', null, 'html');
        $this->assertEquals('html', $p->getMediaSubType());
    }

    public function testConstructorWithResource()
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);
        $p = new TextPart($f);
        $this->assertEquals('content', $p->getBody());
        $this->assertEquals('content', $p->bodyToString());
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        fclose($f);
    }

    public function testConstructorWithFile()
    {
        $p = new TextPart(new File(\dirname(__DIR__).'/Fixtures/content.txt'));
        $this->assertSame('content', $p->getBody());
        $this->assertSame('content', $p->bodyToString());
        $this->assertSame('content', implode('', iterator_to_array($p->bodyToIterable())));
    }

    public function testConstructorWithUnknownFile()
    {
        $p = new TextPart(new File(\dirname(__DIR__).'/Fixtures/unknown.txt'));

        // Exception should be thrown only when the body is accessed
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('{Failed to open stream}');
        $p->getBody();
    }

    public function testConstructorWithNonStringOrResource()
    {
        $this->expectException(\TypeError::class);
        new TextPart(new \stdClass());
    }

    public function testHeaders()
    {
        $p = new TextPart('content');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'quoted-printable')
        ), $p->getPreparedHeaders());

        $p = new TextPart('content', 'iso-8859-1');
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'iso-8859-1']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'quoted-printable')
        ), $p->getPreparedHeaders());
    }

    public function testEncoding()
    {
        $p = new TextPart('content', 'utf-8', 'plain', 'base64');
        $this->assertEquals(base64_encode('content'), $p->bodyToString());
        $this->assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64')
        ), $p->getPreparedHeaders());
    }

    public function testCustomEncoderNeedsToRegisterFirst()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The encoding must be one of "quoted-printable", "base64", "8bit", "upper_encoder" ("this_encoding_does_not_exist" given).');

        $upperEncoder = $this->createMock(ContentEncoderInterface::class);
        $upperEncoder->method('getName')->willReturn('upper_encoder');

        TextPart::addEncoder($upperEncoder);
        new TextPart('content', 'utf-8', 'plain', 'this_encoding_does_not_exist');
    }

    public function testOverwriteDefaultEncoder()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You are not allowed to change the default encoders ("quoted-printable", "base64", and "8bit").');

        $base64Encoder = $this->createMock(ContentEncoderInterface::class);
        $base64Encoder->method('getName')->willReturn('base64');

        TextPart::addEncoder($base64Encoder);
    }

    public function testCustomEncoding()
    {
        TextPart::addEncoder(new class implements ContentEncoderInterface {
            public function encodeByteStream($stream, int $maxLineLength = 0): iterable
            {
                $filter = stream_filter_append($stream, 'string.toupper', \STREAM_FILTER_READ);
                if (!\is_resource($filter)) {
                    throw new RuntimeException('Unable to set the upper content encoder to the filter.');
                }

                while (!feof($stream)) {
                    yield fread($stream, 16372);
                }
                stream_filter_remove($filter);
            }

            public function getName(): string
            {
                return 'upper_encoder';
            }

            public function encodeString(string $string, ?string $charset = 'utf-8', int $firstLineOffset = 0, int $maxLineLength = 0): string
            {
                return strtoupper($string);
            }
        });

        $p = new TextPart('content', 'utf-8', 'plain', 'upper_encoder');
        $this->assertEquals('CONTENT', $p->bodyToString());
        $this->assertEquals('CONTENT', implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'upper_encoder')
        ), $p->getPreparedHeaders());
    }

    public function testSerialize()
    {
        $r = fopen('php://memory', 'r+', false);
        fwrite($r, 'Text content');
        rewind($r);

        $p = new TextPart($r);
        $p->getHeaders()->addTextHeader('foo', 'bar');
        $expected = clone $p;
        $n = unserialize(serialize($p));
        $this->assertEquals($expected->toString(), $p->toString());
        $this->assertEquals($expected->toString(), $n->toString());
    }
}
