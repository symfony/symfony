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
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\TextPart;

class FormDataPartTest extends TestCase
{
    public function testConstructor()
    {
        $r = new \ReflectionProperty(TextPart::class, 'encoding');

        $b = new TextPart('content');
        $c = DataPart::fromPath($file = __DIR__.'/../../Fixtures/mimetypes/test.gif');
        $f = new FormDataPart([
            'foo' => $content = 'very very long content that will not be cut even if the length is way more than 76 characters, ok?',
            'bar' => clone $b,
            'baz' => clone $c,
        ]);
        $this->assertEquals('multipart', $f->getMediaType());
        $this->assertEquals('form-data', $f->getMediaSubtype());
        $t = new TextPart($content, 'utf-8', 'plain', '8bit');
        $t->setDisposition('form-data');
        $t->setName('foo');
        $t->getHeaders()->setMaxLineLength(\PHP_INT_MAX);
        $b->setDisposition('form-data');
        $b->setName('bar');
        $b->getHeaders()->setMaxLineLength(\PHP_INT_MAX);
        $r->setValue($b, '8bit');
        $c->setDisposition('form-data');
        $c->setName('baz');
        $c->getHeaders()->setMaxLineLength(\PHP_INT_MAX);
        $r->setValue($c, '8bit');
        $this->assertEquals([$t, $b, $c], $f->getParts());
    }

    public function testNestedArrayParts()
    {
        $p1 = new TextPart('content', 'utf-8', 'plain', '8bit');
        $f = new FormDataPart([
            'foo' => clone $p1,
            'bar' => [
                'baz' => [
                    clone $p1,
                    'qux' => clone $p1,
                ],
            ],
            'quux' => [
                clone $p1,
                clone $p1,
            ],
            'quuz' => [
                'corge' => [
                    clone $p1,
                    clone $p1,
                ],
            ],
            '2' => clone $p1,
            '0' => clone $p1,

            'bar2' => [
                ['baz' => clone $p1],
                'baz' => [
                    'qux' => clone $p1,
                ],
            ],
            ['quux2' => clone $p1],
            ['quux2' => [clone $p1]],
            'quuz2' => [
                ['corge' => clone $p1],
                ['corge' => clone $p1],
            ],
            ['2' => clone $p1],
            ['2' => clone $p1],
            ['0' => clone $p1],
            ['0' => clone $p1],

            ['2[0]' => clone $p1],
            ['2[1]' => clone $p1],
            ['0[0]' => clone $p1],
            ['0[1]' => clone $p1],

            'qux' => [
                [
                    'foo' => clone $p1,
                    'bar' => clone $p1,
                ],
                [
                    'foo' => clone $p1,
                    'bar' => clone $p1,
                ],
            ],
        ]);

        $this->assertEquals('multipart', $f->getMediaType());
        $this->assertEquals('form-data', $f->getMediaSubtype());

        $parts = [];

        $parts[] = $p1;
        $p1->setName('foo');
        $p1->setDisposition('form-data');

        $parts[] = $p2 = clone $p1;
        $p2->setName('bar[baz][0]');

        $parts[] = $p3 = clone $p1;
        $p3->setName('bar[baz][qux]');

        $parts[] = $p4 = clone $p1;
        $p4->setName('quux[0]');
        $parts[] = $p5 = clone $p1;
        $p5->setName('quux[1]');

        $parts[] = $p6 = clone $p1;
        $p6->setName('quuz[corge][0]');
        $parts[] = $p7 = clone $p1;
        $p7->setName('quuz[corge][1]');

        $parts[] = $p8 = clone $p1;
        $p8->setName('2');

        $parts[] = $p9 = clone $p1;
        $p9->setName('0');

        $parts[] = $p10 = clone $p1;
        $p10->setName('bar2[0][baz]');

        $parts[] = $p11 = clone $p1;
        $p11->setName('bar2[baz][qux]');

        $parts[] = $p12 = clone $p1;
        $p12->setName('quux2');
        $parts[] = $p13 = clone $p1;
        $p13->setName('quux2[0]');

        $parts[] = $p14 = clone $p1;
        $p14->setName('quuz2[0][corge]');
        $parts[] = $p15 = clone $p1;
        $p15->setName('quuz2[1][corge]');

        $parts[] = $p16 = clone $p1;
        $p16->setName('2');
        $parts[] = $p17 = clone $p1;
        $p17->setName('2');

        $parts[] = $p18 = clone $p1;
        $p18->setName('0');
        $parts[] = $p19 = clone $p1;
        $p19->setName('0');

        $parts[] = $p16 = clone $p1;
        $p16->setName('2[0]');
        $parts[] = $p17 = clone $p1;
        $p17->setName('2[1]');

        $parts[] = $p18 = clone $p1;
        $p18->setName('0[0]');
        $parts[] = $p19 = clone $p1;
        $p19->setName('0[1]');

        $parts[] = $p20 = clone $p1;
        $p20->setName('qux[0][foo]');
        $parts[] = $p21 = clone $p1;
        $p21->setName('qux[0][bar]');
        $parts[] = $p22 = clone $p1;
        $p22->setName('qux[1][foo]');
        $parts[] = $p23 = clone $p1;
        $p23->setName('qux[1][bar]');

        $this->assertEquals($parts, $f->getParts());
    }

    public function testExceptionOnFormFieldsWithIntegerKeysAndMultipleValues()
    {
        $p1 = new TextPart('content', 'utf-8', 'plain', '8bit');
        $f = new FormDataPart([
            [
                clone $p1,
                clone $p1,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form field values with integer keys can only have one array element, the key being the field name and the value being the field value, 2 provided.');
        $f->getParts();
    }

    public function testExceptionOnFormFieldsWithDisallowedTypesInsideArray()
    {
        $f = new FormDataPart([
            'foo' => [
                'bar' => 'baz',
                'qux' => [
                    'quux' => 1,
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the form field "foo[qux][quux]" can only be a string, an array, or an instance of TextPart, "int" given.');
        $f->getParts();
    }

    public function testToString()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../../Fixtures/mimetypes/test.gif');
        $this->assertEquals(base64_encode(file_get_contents($file)), $p->bodyToString());
    }

    public function testContentLineLength()
    {
        $f = new FormDataPart([
            'foo' => new DataPart($foo = str_repeat('foo', 1000), 'foo.txt', 'text/plain'),
            'bar' => $bar = str_repeat('bar', 1000),
        ]);
        $parts = $f->getParts();
        $this->assertEquals($foo, $parts[0]->bodyToString());
        $this->assertEquals($bar, $parts[1]->bodyToString());
    }

    public function testBoundaryContentTypeHeader()
    {
        $f = new FormDataPart([
            'file' => new DataPart('data.csv', 'data.csv', 'text/csv'),
        ]);
        $headers = $f->getPreparedHeaders()->toArray();
        $this->assertMatchesRegularExpression(
            '/^Content-Type: multipart\/form-data; boundary=[a-zA-Z0-9\-_]{8}$/',
            $headers[0]
        );
    }

    public function testGetPartsThrowsOnUnexpectedFieldType()
    {
        $dataPart = new FormDataPart(['foo' => new \stdClass()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the form field "foo" can only be a string, an array, or an instance of TextPart, "stdClass" given.');

        $dataPart->getParts();
    }
}
