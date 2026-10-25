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
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

class MixedPartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class MixedPartTest extends TestCase
{
    public function testConstructor()
    {
        $a = new MixedPart();
        $this->assertEquals('multipart', $a->getMediaType());
        $this->assertEquals('mixed', $a->getMediaSubtype());
    }

    public function testSerialize()
    {
        $part = new MixedPart(new AlternativePart(new TextPart('text'), new TextPart('<b>html</b>', 'utf-8', 'html')), new DataPart('data', 'file.bin'));
        $expected = $part->toString();

        $this->assertSame($expected, unserialize(serialize($part))->toString());
    }

    public function testUnserializeNativePayload()
    {
        $payload = $this->createPayload([
            "\0".AbstractPart::class."\0headers" => new Headers(),
            "\0".AbstractMultipartPart::class."\0boundary" => 'native-boundary',
            "\0".AbstractMultipartPart::class."\0parts" => [new TextPart('content')],
        ]);

        $part = unserialize($payload);

        $this->assertEquals([new TextPart('content')], $part->getParts());
        $this->assertStringContainsString("--native-boundary\r\n", $part->toString());
    }

    public function testUnserializeRejectsObjectInBoundary()
    {
        $data = (new MixedPart(new TextPart('content')))->__serialize();
        $data['boundary'] = new MixedPartTestToStringGadget();
        $payload = $this->createPayload($data);
        MixedPartTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(MixedPartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    private function createPayload(array $data): string
    {
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(MixedPart::class), MixedPart::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }

        return $payload.'}';
    }
}
