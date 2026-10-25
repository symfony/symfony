<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

class RelatedPartTest extends TestCase
{
    public function testConstructor()
    {
        $r = new RelatedPart($a = new TextPart('content'), $b = new TextPart('HTML content', 'utf-8', 'html'), $c = new TextPart('HTML content again', 'utf-8', 'html'));
        $this->assertEquals('multipart', $r->getMediaType());
        $this->assertEquals('related', $r->getMediaSubtype());
        $this->assertEquals([$a, $b, $c], $r->getParts());
        $this->assertFalse($a->getHeaders()->has('Content-ID'));
        $this->assertTrue($b->getHeaders()->has('Content-ID'));
        $this->assertTrue($c->getHeaders()->has('Content-ID'));
    }

    public function testSerialize()
    {
        $part = new RelatedPart(new TextPart('<img src="cid:logo">', 'utf-8', 'html'), (new DataPart('image', 'logo', 'image/png'))->asInline());
        $expected = $part->toString();

        $this->assertSame($expected, unserialize(serialize($part))->toString());
    }

    public function testUnserializeNativePayload()
    {
        $data = [
            "\0".AbstractPart::class."\0headers" => new Headers(),
            "\0".AbstractMultipartPart::class."\0boundary" => 'native-boundary',
            "\0".AbstractMultipartPart::class."\0parts" => [new TextPart('related')],
            "\0".RelatedPart::class."\0mainPart" => new TextPart('main'),
        ];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(RelatedPart::class), RelatedPart::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }

        $part = unserialize($payload.'}');

        $this->assertEquals([new TextPart('main'), new TextPart('related')], $part->getParts());
        $this->assertStringContainsString("--native-boundary\r\n", $part->toString());
    }
}
