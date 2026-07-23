<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Transformer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\InvalidArgumentException;
use Symfony\Component\JsonStreamer\Transformer\DateIntervalValueObjectTransformer;

class DateIntervalValueObjectTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $this->assertSame(
            'P0Y0M0DT0H0M0S',
            $transformer->transform(new \DateInterval('P0Y')),
        );

        $this->assertSame(
            'P2Y6M1DT12H30M5S',
            $transformer->transform(new \DateInterval('P2Y6M1DT12H30M5S')),
        );

        $this->assertSame(
            '2 years',
            $transformer->transform(new \DateInterval('P2Y'), [DateIntervalValueObjectTransformer::FORMAT_KEY => '%y years']),
        );
    }

    public function testTransformWithInvertedInterval()
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $interval = new \DateInterval('P1Y');
        $interval->invert = 1;

        $this->assertSame(
            '-P1Y0M0DT0H0M0S',
            $transformer->transform($interval),
        );
    }

    public function testTransformThrowWhenInvalidNativeValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The native value must be an instance of "\DateInterval".');

        (new DateIntervalValueObjectTransformer())->transform(new \stdClass());
    }

    public function testReverseTransform()
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $this->assertEquals(
            new \DateInterval('P2Y6M1DT12H30M5S'),
            $transformer->reverseTransform('P2Y6M1DT12H30M5S'),
        );
    }

    public function testReverseTransformWithSign()
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $expected = new \DateInterval('P1Y');
        $expected->invert = 1;

        $this->assertEquals($expected, $transformer->reverseTransform('-P1Y0M0DT0H0M0S'));

        $this->assertEquals(
            new \DateInterval('P1Y'),
            $transformer->reverseTransform('+P1Y0M0DT0H0M0S', [DateIntervalValueObjectTransformer::FORMAT_KEY => '%RP%yY%mM%dDT%hH%iM%sS']),
        );
    }

    #[DataProvider('formatsAndIntervalsDataProvider')]
    public function testTransformWithFormat(string $format, string $output, string $input)
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $this->assertSame($output, $transformer->transform($this->createInterval($input), [DateIntervalValueObjectTransformer::FORMAT_KEY => $format]));
    }

    #[DataProvider('formatsAndIntervalsDataProvider')]
    public function testReverseTransformWithFormat(string $format, string $input, string $output)
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $this->assertDateIntervalEquals($this->createInterval($output), $transformer->reverseTransform($input, [DateIntervalValueObjectTransformer::FORMAT_KEY => $format]));
    }

    /**
     * @return iterable<array{0: string, 1: string, 2: string}>
     */
    public static function formatsAndIntervalsDataProvider(): iterable
    {
        yield ['P%YY%MM%DDT%HH%IM%SS', 'P00Y00M00DT00H00M00S', 'PT0S'];
        yield ['P%yY%mM%dDT%hH%iM%sS', 'P0Y0M0DT0H0M0S', 'PT0S'];
        yield ['P%yY%mM%dDT%hH%iM%sS', 'P10Y2M3DT16H5M6S', 'P10Y2M3DT16H5M6S'];
        yield ['P%yY%mM%dDT%hH%iM', 'P10Y2M3DT16H5M', 'P10Y2M3DT16H5M'];
        yield ['P%yY%mM%dDT%hH', 'P10Y2M3DT16H', 'P10Y2M3DT16H'];
        yield ['P%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'];
        yield ['%RP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'];
        yield ['%RP%yY%mM%dD', '+P10Y2M3D', '+P10Y2M3DT0H'];
        yield ['%RP%yY%mM%dD', '+P10Y2M3D', 'P10Y2M3DT0H'];
        yield ['%rP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'];
        yield ['%rP%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'];
    }

    public function testReverseTransformWithOmittedPartsBeingZero()
    {
        $transformer = new DateIntervalValueObjectTransformer();

        $this->assertDateIntervalEquals($this->createInterval('P3Y2M4DT0H0M0S'), $transformer->reverseTransform('P3Y2M4D'));
        $this->assertDateIntervalEquals($this->createInterval('P0Y0M0DT12H34M0S'), $transformer->reverseTransform('PT12H34M'));
    }

    public function testReverseTransformThrowWhenInvalidJsonValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value is not a valid ISO 8601 interval string.');

        (new DateIntervalValueObjectTransformer())->reverseTransform(42);
    }

    public function testReverseTransformThrowWhenInvalidIntervalString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value is not a valid ISO 8601 interval string.');

        (new DateIntervalValueObjectTransformer())->reverseTransform('not-an-interval');
    }

    public function testReverseTransformThrowWhenFormatMismatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON value "P1Y0M0DT0H0M0S" contains intervals not accepted by format "%dD".');

        (new DateIntervalValueObjectTransformer())->reverseTransform('P1Y0M0DT0H0M0S', [DateIntervalValueObjectTransformer::FORMAT_KEY => '%dD']);
    }

    private function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual): void
    {
        $this->assertSame($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }

    private function createInterval(string $data): \DateInterval
    {
        $interval = new \DateInterval(ltrim($data, '+-'));
        if ('-' === $data[0]) {
            $interval->invert = 1;
        }

        return $interval;
    }
}
