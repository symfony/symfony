<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;

class DefaultMarshallerTest extends TestCase
{
    public function testSerialize()
    {
        $marshaller = new DefaultMarshaller();
        $values = [
            'a' => 123,
            'b' => static function () {},
        ];

        $expected = ['a' => \extension_loaded('phpser') ? phpser_serialize(123) : serialize(123)];
        $this->assertSame($expected, $marshaller->marshall($values, $failed));
        $this->assertSame(['b'], $failed);
    }

    public function testNativeSerialize()
    {
        $this->assertSame(['a' => serialize(123)], (new DefaultMarshaller(false))->marshall(['a' => 123], $failed));
        $this->assertSame([], $failed);
    }

    #[RequiresPhpExtension('phpser')]
    #[DataProvider('providePhpSerValues')]
    public function testPhpSerUnserialize(mixed $value)
    {
        $this->assertSame($value, (new DefaultMarshaller())->unmarshall(phpser_serialize($value)));
    }

    public static function providePhpSerValues(): iterable
    {
        yield [null];
        yield [false];
        yield [''];
        yield [0];
        yield ["\x01\x00\x00"];
        yield [['a' => [true, 1.5, 'hello']]];
    }

    #[RequiresPhpExtension('phpser')]
    public function testPhpSerUnserializeWithColonInHeader()
    {
        $value = array_map(static fn ($i) => 'string'.$i, range(0, 57));
        $value = [...$value, ...$value];
        $serialized = phpser_serialize($value);
        $this->assertSame(':', $serialized[1]);
        $this->assertSame($value, (new DefaultMarshaller())->unmarshall($serialized));
    }

    #[RequiresPhpExtension('phpser')]
    public function testPhpSerObjects()
    {
        $value = new DefaultMarshallerPayload();

        foreach ([null, str_repeat('k', 32)] as $key) {
            $marshaller = new DefaultMarshaller(null, false, $key);
            $serialized = $marshaller->marshall(['a' => [$value, $value]], $failed);
            $this->assertSame([], $failed);
            $this->assertSame("\x02", $serialized['a'][0]);
            $decoded = $marshaller->unmarshall($serialized['a']);
            $this->assertEquals([$value, $value], $decoded);
            $this->assertSame($decoded[0], $decoded[1]);
        }
    }

    #[RequiresPhpExtension('phpser')]
    #[DataProvider('providePhpSerValues')]
    public function testSignedPhpSer(mixed $value)
    {
        $key = str_repeat('k', 32);
        $marshaller = new DefaultMarshaller(null, false, $key);
        $serialized = $marshaller->marshall(['a' => $value], $failed);

        $this->assertSame([], $failed);
        $this->assertSame(phpser_serialize_signed($value, $key), $serialized['a']);
        $this->assertSame($value, $marshaller->unmarshall($serialized['a']));
    }

    #[RequiresPhpExtension('phpser')]
    #[DataProvider('provideInvalidSignedValues')]
    public function testSignedPhpSerRejectsInvalidValues(string $type)
    {
        $key = str_repeat('k', 32);
        $value = match ($type) {
            'native' => serialize(new DefaultMarshallerWakeup()),
            'unsigned' => phpser_serialize(new DefaultMarshallerWakeup()),
            'wrong key' => phpser_serialize_signed(new DefaultMarshallerWakeup(), str_repeat('x', 32)),
            'tampered' => phpser_serialize_signed(new DefaultMarshallerWakeup(), $key),
            'empty' => '',
            'null' => 'N;',
            'false' => 'b:0;',
        };
        if ('tampered' === $type) {
            $value[-1] = ~$value[-1];
        }

        $this->expectException(\DomainException::class);
        (new DefaultMarshaller(null, false, $key))->unmarshall($value);
    }

    public static function provideInvalidSignedValues(): iterable
    {
        foreach (['native', 'unsigned', 'wrong key', 'tampered', 'empty', 'null', 'false'] as $type) {
            yield $type => [$type];
        }
    }

    #[RequiresPhpExtension('phpser')]
    public function testPhpSerUnserializeInvalid()
    {
        $this->expectException(\DomainException::class);
        (new DefaultMarshaller())->unmarshall("\x01\x00\xff");
    }

    #[RequiresPhpExtension('phpser')]
    public function testSigningKeyTooShort()
    {
        $this->expectException(InvalidArgumentException::class);
        new DefaultMarshaller(null, false, str_repeat('k', 31));
    }

    public function testSigningRequiresPhpSer()
    {
        if (\extension_loaded('phpser')) {
            $this->markTestSkipped('The "phpser" extension is loaded.');
        }

        $this->expectException(CacheException::class);
        $this->expectExceptionMessage('The "phpser" PHP extension is not loaded.');
        new DefaultMarshaller(null, false, str_repeat('k', 32));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('igbinary')]
    public function testIgbinarySerialize()
    {
        if (version_compare('3.1.6', phpversion('igbinary'), '>')) {
            $this->markTestSkipped('igbinary needs to be v3.1.6 or higher.');
        }

        $this->expectUserDeprecationMessage('Since symfony/cache 8.2: Using igbinary in "Symfony\Component\Cache\Marshaller\DefaultMarshaller" is deprecated, use the "phpser" extension instead.');
        $marshaller = new DefaultMarshaller(true);
        $values = [
            'a' => 123,
            'b' => static function () {},
        ];

        $expected = ['a' => igbinary_serialize(123)];
        $this->assertSame($expected, $marshaller->marshall($values, $failed));
        $this->assertSame(['b'], $failed);
    }

    public function testNativeUnserialize()
    {
        $marshaller = new DefaultMarshaller();
        $this->assertNull($marshaller->unmarshall(serialize(null)));
        $this->assertFalse($marshaller->unmarshall(serialize(false)));
        $this->assertSame('', $marshaller->unmarshall(serialize('')));
        $this->assertSame(0, $marshaller->unmarshall(serialize(0)));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('igbinary')]
    public function testIgbinaryUnserialize()
    {
        if (version_compare('3.1.6', phpversion('igbinary'), '>')) {
            $this->markTestSkipped('igbinary needs to be v3.1.6 or higher.');
        }

        $marshaller = new DefaultMarshaller();
        $this->assertNull($marshaller->unmarshall(igbinary_serialize(null)));
        $this->assertFalse($marshaller->unmarshall(igbinary_serialize(false)));
        $this->assertSame('', $marshaller->unmarshall(igbinary_serialize('')));
        $this->assertSame(0, $marshaller->unmarshall(igbinary_serialize(0)));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('igbinary')]
    public function testIgbinaryUnserializeIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/cache 8.2: Unmarshalling igbinary values in "Symfony\Component\Cache\Marshaller\DefaultMarshaller" is deprecated, use the "phpser" extension instead.');
        $this->assertNull((new DefaultMarshaller())->unmarshall(igbinary_serialize(null)));
    }

    public function testNativeUnserializeNotFoundClass()
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Class not found: NotExistingClass');
        (new DefaultMarshaller())->unmarshall('O:16:"NotExistingClass":0:{}');
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('igbinary')]
    public function testIgbinaryUnserializeNotFoundClass()
    {
        if (version_compare('3.1.6', phpversion('igbinary'), '>')) {
            $this->markTestSkipped('igbinary needs to be v3.1.6 or higher.');
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Class not found: NotExistingClass');
        $marshaller = new DefaultMarshaller();
        $marshaller->unmarshall(rawurldecode('%00%00%00%02%17%10NotExistingClass%14%00'));
    }

    public function testNativeUnserializeInvalid()
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unserialize(): Error at offset 0 of 3 bytes');
        $marshaller = new DefaultMarshaller();
        set_error_handler(static fn () => false);
        try {
            @$marshaller->unmarshall(':::');
        } finally {
            restore_error_handler();
        }
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RequiresPhpExtension('igbinary')]
    public function testIgbinaryUnserializeInvalid()
    {
        if (version_compare('3.1.6', phpversion('igbinary'), '>')) {
            $this->markTestSkipped('igbinary needs to be v3.1.6 or higher.');
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('igbinary_unserialize_zval: unknown type \'61\', position 5');
        $marshaller = new DefaultMarshaller();
        set_error_handler(static fn () => false);
        try {
            @$marshaller->unmarshall(rawurldecode('%00%00%00%02abc'));
        } finally {
            restore_error_handler();
        }
    }

    public function testSerializeDebug()
    {
        $marshaller = new DefaultMarshaller(false, true);
        $values = [
            'a' => static function () {},
        ];

        $this->expectException(\ValueError::class);
        $marshaller->marshall($values, $failed);
    }
}

class DefaultMarshallerWakeup
{
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Unverified data must not be decoded.');
    }
}

class DefaultMarshallerPayload
{
    public int $value = 123;
}
