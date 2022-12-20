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
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\TextPart;

class TextPartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new TextPart('content');
        self::assertEquals('content', $p->getBody());
        self::assertEquals('content', $p->bodyToString());
        self::assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        self::assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals('text', $p->getMediaType());
        self::assertEquals('plain', $p->getMediaSubType());

        $p = new TextPart('content', null, 'html');
        self::assertEquals('html', $p->getMediaSubType());
    }

    public function testConstructorWithResource()
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);
        $p = new TextPart($f);
        self::assertEquals('content', $p->getBody());
        self::assertEquals('content', $p->bodyToString());
        self::assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        fclose($f);
    }

    public function testConstructorWithNonStringOrResource()
    {
        self::expectException(\TypeError::class);
        new TextPart(new \stdClass());
    }

    public function testHeaders()
    {
        $p = new TextPart('content');
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'quoted-printable')
        ), $p->getPreparedHeaders());

        $p = new TextPart('content', 'iso-8859-1');
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'iso-8859-1']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'quoted-printable')
        ), $p->getPreparedHeaders());
    }

    public function testEncoding()
    {
        $p = new TextPart('content', 'utf-8', 'plain', 'base64');
        self::assertEquals(base64_encode('content'), $p->bodyToString());
        self::assertEquals(base64_encode('content'), implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'text/plain', ['charset' => 'utf-8']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64')
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
        self::assertEquals($expected->toString(), $p->toString());
        self::assertEquals($expected->toString(), $n->toString());
    }
}
