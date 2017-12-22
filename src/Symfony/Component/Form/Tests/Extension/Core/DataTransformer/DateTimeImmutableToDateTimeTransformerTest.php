<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\DataTransformer;

use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToDateTimeTransformer;

class DateTimeImmutableToDateTimeTransformerTest extends DateTimeTestCase
{
    public function testTransform()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $input = new \DateTimeImmutable('2010-02-03 04:05:06 UTC');
        $output = new \DateTime('2010-02-03 04:05:06 UTC');

        $this->assertDateTimeEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->transform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
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
        $output = new \DateTimeImmutable('2010-02-03 04:05:06 UTC');

        $this->assertDateTimeImmutableEquals($output, $transformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Expected a \DateTime.
     */
    public function testReverseTransformFail()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();
        $transformer->reverseTransform(new \DateTimeImmutable());
    }
}
