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

    protected function setUp()
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
        $this->assertEquals($output, $this->normalizer->normalize(new \DateInterval($input), null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testNormalizeUsingFormatPassedInConstructor($format, $output, $input)
    {
        $this->assertEquals($output, (new DateIntervalNormalizer($format))->normalize(new \DateInterval($input)));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage The object must be an instance of "\DateInterval".
     */
    public function testNormalizeInvalidObjectThrowsException()
    {
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
        $this->assertDateIntervalEquals(new \DateInterval($output), $this->normalizer->denormalize($input, \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => $format]));
    }

    /**
     * @dataProvider dataProviderISO
     */
    public function testDenormalizeUsingFormatPassedInConstructor($format, $input, $output)
    {
        $this->assertDateIntervalEquals(new \DateInterval($output), (new DateIntervalNormalizer($format))->denormalize($input, \DateInterval::class));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testDenormalizeExpectsString()
    {
        $this->normalizer->denormalize(1234, \DateInterval::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @expectedExceptionMessage Expected a valid ISO 8601 interval string.
     */
    public function testDenormalizeNonISO8601IntervalStringThrowsException()
    {
        $this->normalizer->denormalize('10 years 2 months 3 days', \DateInterval::class, null);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeInvalidDataThrowsException()
    {
        $this->normalizer->denormalize('invalid interval', \DateInterval::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeFormatMismatchThrowsException()
    {
        $this->normalizer->denormalize('P00Y00M00DT00H00M00S', \DateInterval::class, null, [DateIntervalNormalizer::FORMAT_KEY => 'P%yY%mM%dD']);
    }

    private function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual)
    {
        $this->assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }
}
