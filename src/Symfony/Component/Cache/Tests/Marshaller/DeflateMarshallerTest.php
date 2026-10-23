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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
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

    public function testNestedMarshallers()
    {
        $marshaller = new DeflateMarshaller(new DeflateMarshaller(new DefaultMarshaller()));
        $values = ['abc' => str_repeat('cache value ', 100)];

        $result = $marshaller->marshall($values, $failed);

        $this->assertSame($values['abc'], $marshaller->unmarshall($result['abc']));
    }

    public function testFailedValues()
    {
        $marshaller = new DeflateMarshaller(new DefaultMarshaller());

        $result = $marshaller->marshall([23 => 'cache value', 'failed' => static fn () => null], $failed);

        $this->assertSame([23 => serialize('cache value')], $result);
        $this->assertSame(['failed'], $failed);
    }
}
