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
use Symfony\Component\Mime\Part\SMimePart;

class SMimePartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new SMimePart('content', 'multipart', 'signed', []);
        $this->assertEquals('content', $p->bodyToString());
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('multipart', $p->getMediaType());
        $this->assertEquals('signed', $p->getMediaSubType());
    }

    public function testConstructorWithIterable()
    {
        $iterable = $this->getIterable();

        $p = new SMimePart($iterable, 'multipart', 'signed', []);
        $this->assertEquals('content', $p->bodyToString());
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals('content', implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('multipart', $p->getMediaType());
        $this->assertEquals('signed', $p->getMediaSubType());
    }

    public function testConstructorWithInvalidIterable()
    {
        $iterable = $this->getIterable();
        // We using this method for close a generator which should throws: Exception : Cannot traverse an already closed generator
        \iterator_to_array($iterable);

        $p = new SMimePart($iterable, 'multipart', 'signed', []);
        $this->assertEquals('', $p->bodyToString());
        $this->assertEquals('', implode('', iterator_to_array($p->bodyToIterable())));
        // bodyToIterable() can be called several times
        $this->assertEquals('', implode('', iterator_to_array($p->bodyToIterable())));
        $this->assertEquals('multipart', $p->getMediaType());
        $this->assertEquals('signed', $p->getMediaSubType());
    }

    public function testConstructorWithNonStringOrIterable()
    {
        $this->expectException(\TypeError::class);
        new SMimePart(new \stdClass(), 'multipart', 'signed', []);
    }

    public function testHeaders()
    {
        $p = new SMimePart('content', 'multipart', 'signed', []);
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'multipart/signed', [])
        ), $p->getPreparedHeaders());

        $p = new SMimePart('content', 'multipart', 'signed', ['charset' => 'utf-8']);
        $this->assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'multipart/signed', ['charset' => 'utf-8'])
        ), $p->getPreparedHeaders());
    }

    private function getIterable(): iterable
    {
        $f = fopen('php://memory', 'r+', false);
        fwrite($f, 'content');
        rewind($f);

        while (!feof($f)) {
            yield fread($f, 2);
        }

        fclose($f);
    }
}
