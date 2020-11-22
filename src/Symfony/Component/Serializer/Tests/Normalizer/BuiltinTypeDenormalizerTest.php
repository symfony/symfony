<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\BuiltinTypeDenormalizer;

class BuiltinTypeDenormalizerTest extends TestCase
{
    /**
     * @var BuiltinTypeDenormalizer
     */
    private $denormalizer;

    protected function setUp(): void
    {
        $this->denormalizer = new BuiltinTypeDenormalizer();
    }

    /**
     * @dataProvider provideSupportedTypes
     */
    public function testSupportsDenormalization(string $supportedType): void
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization(null, $supportedType));
    }

    public function provideSupportedTypes(): iterable
    {
        return [['int'], ['float'], ['string'], ['bool'], ['resource'], ['callable']];
    }

    /**
     * @dataProvider provideUnsupportedTypes
     */
    public function testUnsupportsDenormalization(string $unsupportedType): void
    {
        $this->assertFalse($this->denormalizer->supportsDenormalization(null, $unsupportedType));
    }

    public function provideUnsupportedTypes(): iterable
    {
        return [['null'], ['array'], ['iterable'], ['object'], ['int[]']];
    }

    /**
     * @dataProvider provideInvalidData
     */
    public function testDenormalizeInvalidDataThrowsException($invalidData): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->denormalizer->denormalize($invalidData, 'int');
    }

    public function provideInvalidData(): iterable
    {
        return [
            'array' => [[1, 2]],
            'object' => [new \stdClass()],
            'null' => [null],
        ];
    }

    /**
     * @dataProvider provideNotNormalizableData
     */
    public function testDenormalizeNotNormalizableDataThrowsException($data, string $type, string $format): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->denormalizer->denormalize($data, $type, $format);
    }

    public function provideNotNormalizableData(): iterable
    {
        return [
            'not a string' => [true, 'string', 'json'],
            'not an integer' => [3.1, 'int', 'json'],
            'not an integer (xml/csv)' => ['+12', 'int', 'xml'],
            'not a float' => [false, 'float', 'json'],
            'not a float (xml/csv)' => ['nan', 'float', 'xml'],
            'not a boolean (json)' => [0, 'bool', 'json'],
            'not a boolean (xml/csv)' => ['test', 'bool', 'xml'],
        ];
    }

    /**
     * @dataProvider provideNormalizableData
     */
    public function testDenormalize($expectedResult, $data, string $type, string $format = null): void
    {
        $result = $this->denormalizer->denormalize($data, $type, $format);

        if (\is_float($expectedResult) && is_nan($expectedResult)) {
            $this->assertNan($result);
        } else {
            $this->assertSame($expectedResult, $result);
        }
    }

    public function provideNormalizableData(): iterable
    {
        return [
            'string' => ['1', '1', 'string', 'json'],
            'integer' => [-3, -3, 'int', 'json'],
            'integer (xml/csv)' => [-12, '-12', 'int', 'xml'],
            'float' => [3.14, 3.14, 'float', 'json'],
            'float without decimals' => [3.0, 3, 'float', 'json'],
            'NaN (xml/csv)' => [\NAN, 'NaN', 'float', 'xml'],
            'INF (xml/csv)' => [\INF, 'INF', 'float', 'xml'],
            '-INF (xml/csv)' => [-\INF, '-INF', 'float', 'xml'],
            'boolean: true (json)' => [true, true, 'bool', 'json'],
            'boolean: false (json)' => [false, false, 'bool', 'json'],
            "boolean: 'true' (xml/csv)" => [true, 'true', 'bool', 'xml'],
            "boolean: '1' (xml/csv)" => [true, '1', 'bool', 'xml'],
            "boolean: 'false' (xml/csv)" => [false, 'false', 'bool', 'xml'],
            "boolean: '0' (xml/csv)" => [false, '0', 'bool', 'xml'],
            'callable' => [[$this, 'provideInvalidData'], [$this, 'provideInvalidData'], 'callable', null],
            'resource' => [$r = fopen(__FILE__, 'r'), $r, 'resource', null],
        ];
    }
}
