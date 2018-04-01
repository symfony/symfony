<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\DataTransformer;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToDateTimeTransformer;

class DateTimeImmutableToDateTimeTransformerTest extends TestCase
{
    public function testTransform()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 UTC');
        $expectedOutput = new \DateTime('2010-02-03 04:05:06 UTC');
        $actualOutput = $transformer->transform($input);

        $this->assertInstanceOf(\DateTime::class, $actualOutput);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->transform(null));
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Expected a \DateTimeImmutable.
     */
    public function testTransformFail()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();
        $transformer->transform(new \DateTime());
    }

    public function testReverseTransform()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $input = new \DateTime('2010-02-03 04:05:06 UTC');
        $expectedOutput = new \DateTimeImmutable('2010-02-03 04:05:06 UTC');
        $actualOutput = $transformer->reverseTransform($input);

        $this->assertInstanceOf(\DateTimeImmutable::class, $actualOutput);
        $this->assertEquals($expectedOutput, $actualOutput);
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Expected a \DateTime.
     */
    public function testReverseTransformFail()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();
        $transformer->reverseTransform(new \DateTimeImmutable());
    }
}
