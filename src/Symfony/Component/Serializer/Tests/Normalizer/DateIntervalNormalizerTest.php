<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;

/**
 * @author Jérôme Parmentier <jerome@prmntr.me>
 */
class DateIntervalNormalizerTest extends TestCase
{
    /**
     * @var DateIntervalNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DateIntervalNormalizer();
    }

    public function dataProviderISO()
    {
        $data = [
            ['P%YY%MM%DDT%HH%IM%SS', 'P00Y00M00DT00H00M00S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P0Y0M0DT0H0M0S', 'PT0S'],
            ['P%yY%mM%dDT%hH%iM%sS', 'P10Y2M3DT16H5M6S', 'P10Y2M3DT16H5M6S'],
            ['P%yY%mM%dDT%hH%iM', 'P10Y2M3DT16H5M', 'P10Y2M3DT16H5M'],
            ['P%yY%mM%dDT%hH', 'P10Y2M3DT16H', 'P10Y2M3DT16H'],
            ['P%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '+P10Y2M3D', '+P10Y2M3DT0H'],
            ['%RP%yY%mM%dD', '+P10Y2M3D', 'P10Y2M3DT0H'],
            ['%rP%yY%mM%dD', '-P10Y2M3D', '-P10Y2M3DT0H'],
            ['%rP%yY%mM%dD', 'P10Y2M3D', 'P10Y2M3DT0H'],
        ];

        return $data;
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new \DateInterval('P00Y00M00DT00H00M00S')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertEquals('P0Y0M0DT0H0M0S', $this->normalizer->normalize(new \DateInterval('PT0S')));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testNormalizeUsingFormatPassedInContext($format, $output, $input)
    {
        $this->assertEquals($output, $this->normalizer->normalize($this->getInterval($input), null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testNormalizeUsingFormatPassedInConstructor($format, $output, $input)
    {
        $normalizer = new DateIntervalNormalizer([DateIntervalNormalizer::FORMAT_KEY => $format]);
        $this->assertEquals($output, $normalizer->normalize($this->getInterval($input)));
    }

    public function testNormalizeInvalidObjectThrowsException()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The object must be an instance of "\DateInterval".');
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('P00Y00M00DT00H00M00S', \DateInterval::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', 'Bar'));
    }

    public function testDenormalize()
    {
        $this->assertDateIntervalEquals(new \DateInterval('P00Y00M00DT00H00M00S'), $this->normalizer->denormalize('P00Y00M00DT00H00M00S', \DateInterval::class));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testDenormalizeUsingFormatPassedInContext($format, $input, $output)
    {
        $this->assertDateIntervalEquals($this->getInterval($output), $this->normalizer->denormalize($input, \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testDenormalizeUsingFormatPassedInConstructor($format, $input, $output)
    {
        $normalizer = new DateIntervalNormalizer([DateIntervalNormalizer::FORMAT_KEY => $format]);
        $this->assertDateIntervalEquals($this->getInterval($output), $normalizer->denormalize($input, \DateInterval::class));
    }

    public function testDenormalizeExpectsString()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\InvalidArgumentException');
        $this->normalizer->denormalize(1234, \DateInterval::class);
    }

    public function testDenormalizeNonISO8601IntervalStringThrowsException()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\UnexpectedValueException');
        $this->expectExceptionMessage('Expected a valid ISO 8601 interval string.');
        $this->normalizer->denormalize('10 years 2 months 3 days', \DateInterval::class, null);
    }

    public function testDenormalizeInvalidDataThrowsException()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\UnexpectedValueException');
        $this->normalizer->denormalize('invalid interval', \DateInterval::class);
    }

    public function testDenormalizeFormatMismatchThrowsException()
    {
        $this->expectException('Symfony\Component\Serializer\Exception\UnexpectedValueException');
        $this->normalizer->denormalize('P00Y00M00DT00H00M00S', \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => 'P%yY%mM%dD']);
    }

    private function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual)
    {
        $this->assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }

    private function getInterval($data)
    {
        if ('-' === $data[0]) {
            $interval = new \DateInterval(substr($data, 1));
            $interval->invert = 1;

            return $interval;
        }

        if ('+' === $data[0]) {
            return new \DateInterval(substr($data, 1));
        }

        return new \DateInterval($data);
    }
}
