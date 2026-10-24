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
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\DeflateMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

#[RequiresPhpExtension('zlib')]
class DeflateMarshallerTest extends TestCase
{
    public function testMarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $deflateMarshaller = new DeflateMarshaller($defaultMarshaller);

        $values = ['abc' => [str_repeat('def', 100)]];

        $failed = [];
        $defaultResult = $defaultMarshaller->marshall($values, $failed);

        $deflateResult = $deflateMarshaller->marshall($values, $failed);
        $deflateResult['abc'] = gzinflate($deflateResult['abc']);

        $this->assertSame($defaultResult, $deflateResult);
    }

    public function testUnmarshall()
    {
        $defaultMarshaller = new DefaultMarshaller();
        $deflateMarshaller = new DeflateMarshaller($defaultMarshaller);

        $values = ['abc' => [str_repeat('def', 100)]];

        $defaultResult = $defaultMarshaller->marshall($values, $failed);
        $deflateResult = $deflateMarshaller->marshall($values, $failed);

        $this->assertSame($values['abc'], $deflateMarshaller->unmarshall($deflateResult['abc']));
        $this->assertSame($values['abc'], $deflateMarshaller->unmarshall($defaultResult['abc']));
    }

    #[DataProvider('provideAdaptiveCompressionValues')]
    public function testAdaptiveCompression(string $value, bool $compressed)
    {
        $marshaller = $this->createStub(MarshallerInterface::class);
        $marshaller->method('marshall')->willReturn(['abc' => $value]);
        $marshaller->method('unmarshall')->willReturnCallback(static fn ($value) => $value);
        $deflateMarshaller = new DeflateMarshaller($marshaller);

        $result = $deflateMarshaller->marshall(['abc' => $value], $failed);

        $this->assertSame(['abc' => $compressed ? gzdeflate($value) : $value], $result);
        $this->assertSame($value, $deflateMarshaller->unmarshall($result['abc']));
    }

    public static function provideAdaptiveCompressionValues(): iterable
    {
        $binary = hash('sha512', 'cache value', true).hash('sha512', 'another cache value', true);

        yield 'empty' => ['', false];
        yield 'null' => [serialize(null), false];
        yield 'false' => [serialize(false), false];
        yield 'short string' => [serialize('cache value'), false];
        yield 'below threshold' => [str_repeat('a', 31), false];
        yield 'at threshold' => [str_repeat('a', 32), true];
        yield 'compressible' => [str_repeat('cache value ', 100), true];
        yield 'incompressible' => [hash('sha512', 'cache value', true), false];
        yield 'below probe limit' => [substr($binary, 0, 127), false];
        yield 'at probe limit' => [$binary, false];
        yield 'above probe limit' => [$binary."\x00", false];
        yield 'empty deflate stream' => ["\x03\x00", true];
        yield 'truncated empty deflate stream' => ["\x03", true];
        yield 'deflate stream' => [gzdeflate('cache value'), true];
        yield 'deflate stream with stored block' => [gzdeflate(hash('sha512', 'cache value', true), 0), true];
        yield 'deflate stream across probe limit' => [gzdeflate($binary, 0), true];
    }

    #[DataProvider('provideCompressionLevels')]
    public function testCompressionLevel(int $compressionLevel)
    {
        $value = str_repeat('cache value ', 128);
        $marshaller = $this->createStub(MarshallerInterface::class);
        $marshaller->method('marshall')->willReturn(['abc' => $value]);
        $marshaller->method('unmarshall')->willReturnCallback(static fn ($value) => $value);
        $deflateMarshaller = new DeflateMarshaller($marshaller, $compressionLevel);

        $result = $deflateMarshaller->marshall(['abc' => $value], $failed);

        $this->assertSame(['abc' => 0 === $compressionLevel ? $value : gzdeflate($value, $compressionLevel)], $result);
        $this->assertSame($value, (new DeflateMarshaller($marshaller))->unmarshall($result['abc']));
        $this->assertSame($value, $deflateMarshaller->unmarshall(gzdeflate($value)));
    }

    #[DataProvider('provideCompressionLevels')]
    public function testAlreadyCompressedValueWithCompressionLevel(int $compressionLevel)
    {
        $value = gzdeflate('cache value');
        $marshaller = $this->createStub(MarshallerInterface::class);
        $marshaller->method('marshall')->willReturn(['abc' => $value]);
        $marshaller->method('unmarshall')->willReturnCallback(static fn ($value) => $value);
        $deflateMarshaller = new DeflateMarshaller($marshaller, $compressionLevel);

        $result = $deflateMarshaller->marshall(['abc' => $value], $failed);

        $this->assertSame(['abc' => gzdeflate($value, $compressionLevel)], $result);
        $this->assertSame($value, (new DeflateMarshaller($marshaller))->unmarshall($result['abc']));
    }

    public static function provideCompressionLevels(): iterable
    {
        yield 'default' => [-1];
        yield 'no compression' => [0];
        yield 'fastest' => [1];
        yield 'smallest' => [9];
    }

    #[DataProvider('provideInvalidCompressionLevels')]
    public function testInvalidCompressionLevel(int $compressionLevel)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The compression level must be between -1 and 9, "%d" given.', $compressionLevel));

        new DeflateMarshaller(new DefaultMarshaller(), $compressionLevel);
    }

    public static function provideInvalidCompressionLevels(): iterable
    {
        yield 'below minimum' => [-2];
        yield 'above maximum' => [10];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNestedMarshallers()
    {
        $this->expectUserDeprecationMessage('Since symfony/cache 8.3: Nesting "Symfony\Component\Cache\Marshaller\DeflateMarshaller" decorators is deprecated. Use a single decorator instead.');

        $marshaller = new DeflateMarshaller(new DeflateMarshaller(new DefaultMarshaller()));
        $values = ['abc' => str_repeat('cache value ', 100)];

        $result = $marshaller->marshall($values, $failed);

        $this->assertSame($values['abc'], $marshaller->unmarshall($result['abc']));
        $this->assertSame($values['abc'], $marshaller->unmarshall(gzdeflate(gzdeflate(serialize($values['abc'])))));
    }

    public function testFailedValues()
    {
        $marshaller = new DeflateMarshaller(new DefaultMarshaller());

        $result = $marshaller->marshall([23 => 'cache value', 'failed' => static fn () => null], $failed);

        $this->assertSame([23 => serialize('cache value')], $result);
        $this->assertSame(['failed'], $failed);
    }
}
