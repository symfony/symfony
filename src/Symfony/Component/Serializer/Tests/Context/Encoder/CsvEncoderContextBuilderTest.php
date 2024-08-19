<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\Context\Encoder\CsvEncoderContextBuilder;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class CsvEncoderContextBuilderTest extends TestCase
{
    use ExpectDeprecationTrait;

    private CsvEncoderContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new CsvEncoderContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withDelimiter($values[CsvEncoder::DELIMITER_KEY])
            ->withEnclosure($values[CsvEncoder::ENCLOSURE_KEY])
            ->withKeySeparator($values[CsvEncoder::KEY_SEPARATOR_KEY])
            ->withHeaders($values[CsvEncoder::HEADERS_KEY])
            ->withEscapedFormulas($values[CsvEncoder::ESCAPE_FORMULAS_KEY])
            ->withAsCollection($values[CsvEncoder::AS_COLLECTION_KEY])
            ->withNoHeaders($values[CsvEncoder::NO_HEADERS_KEY])
            ->withEndOfLine($values[CsvEncoder::END_OF_LINE])
            ->withOutputUtf8Bom($values[CsvEncoder::OUTPUT_UTF8_BOM_KEY])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            CsvEncoder::DELIMITER_KEY => ';',
            CsvEncoder::ENCLOSURE_KEY => '"',
            CsvEncoder::KEY_SEPARATOR_KEY => '_',
            CsvEncoder::HEADERS_KEY => ['h1', 'h2'],
            CsvEncoder::ESCAPE_FORMULAS_KEY => true,
            CsvEncoder::AS_COLLECTION_KEY => true,
            CsvEncoder::NO_HEADERS_KEY => false,
            CsvEncoder::END_OF_LINE => 'EOL',
            CsvEncoder::OUTPUT_UTF8_BOM_KEY => false,
        ]];

        yield 'With null values' => [[
            CsvEncoder::DELIMITER_KEY => null,
            CsvEncoder::ENCLOSURE_KEY => null,
            CsvEncoder::KEY_SEPARATOR_KEY => null,
            CsvEncoder::HEADERS_KEY => null,
            CsvEncoder::ESCAPE_FORMULAS_KEY => null,
            CsvEncoder::AS_COLLECTION_KEY => null,
            CsvEncoder::NO_HEADERS_KEY => null,
            CsvEncoder::END_OF_LINE => null,
            CsvEncoder::OUTPUT_UTF8_BOM_KEY => null,
        ]];
    }

    public function testWithersWithoutValue()
    {
        $context = $this->contextBuilder
            ->withDelimiter(null)
            ->withEnclosure(null)
            ->withKeySeparator(null)
            ->withHeaders(null)
            ->withEscapedFormulas(null)
            ->withAsCollection(null)
            ->withNoHeaders(null)
            ->withEndOfLine(null)
            ->withOutputUtf8Bom(null)
            ->toArray();

        $this->assertSame([
            CsvEncoder::DELIMITER_KEY => null,
            CsvEncoder::ENCLOSURE_KEY => null,
            CsvEncoder::KEY_SEPARATOR_KEY => null,
            CsvEncoder::HEADERS_KEY => null,
            CsvEncoder::ESCAPE_FORMULAS_KEY => null,
            CsvEncoder::AS_COLLECTION_KEY => null,
            CsvEncoder::NO_HEADERS_KEY => null,
            CsvEncoder::END_OF_LINE => null,
            CsvEncoder::OUTPUT_UTF8_BOM_KEY => null,
        ], $context);
    }

    public function testCannotSetMultipleBytesAsDelimiter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withDelimiter('ọ');
    }

    public function testCannotSetMultipleBytesAsEnclosure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withEnclosure('ọ');
    }

    /**
     * @group legacy
     */
    public function testCannotSetMultipleBytesAsEscapeChar()
    {
        $this->expectDeprecation('Since symfony/serializer 7.2: The "Symfony\Component\Serializer\Context\Encoder\CsvEncoderContextBuilder::withEscapeChar" method is deprecated. It will be removed in 8.0.');

        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withEscapeChar('ọ');
    }

    /**
     * @group legacy
     */
    public function testWithEscapeCharIsDeprecated()
    {
        $this->expectDeprecation('Since symfony/serializer 7.2: The "Symfony\Component\Serializer\Context\Encoder\CsvEncoderContextBuilder::withEscapeChar" method is deprecated. It will be removed in 8.0.');
        $context = $this->contextBuilder->withEscapeChar('\\');

        $this->assertSame(['csv_escape_char' => '\\'], $context->toArray());
    }
}
