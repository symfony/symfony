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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToDateTimeTransformer;

class DateTimeImmutableToDateTimeTransformerTest extends TestCase
{
    /**
     * @dataProvider provider
     */
    public function testTransform(\DateTime $expectedOutput, \DateTimeImmutable $input)
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $actualOutput = $transformer->transform($input);

        $this->assertEquals($expectedOutput, $actualOutput);
        $this->assertEquals($expectedOutput->getTimezone(), $actualOutput->getTimezone());
    }

    public function provider()
    {
        return [
            [
                new \DateTime('2010-02-03 04:05:06 UTC'),
                new \DateTimeImmutable('2010-02-03 04:05:06 UTC'),
            ],
            [
                (new \DateTime('2019-10-07 +11:00'))
                    ->setTime(14, 27, 11, 10042),
                (new \DateTimeImmutable('2019-10-07 +11:00'))
                    ->setTime(14, 27, 11, 10042),
            ],
        ];
    }

    public function testTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->transform(null));
    }

    public function testTransformFail()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('Expected a \DateTimeImmutable.');
        $transformer = new DateTimeImmutableToDateTimeTransformer();
        $transformer->transform(new \DateTime());
    }

    /**
     * @dataProvider provider
     */
    public function testReverseTransform(\DateTime $input, \DateTimeImmutable $expectedOutput)
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $actualOutput = $transformer->reverseTransform($input);

        $this->assertEquals($expectedOutput, $actualOutput);
        $this->assertEquals($expectedOutput->getTimezone(), $actualOutput->getTimezone());
    }

    public function testReverseTransformEmpty()
    {
        $transformer = new DateTimeImmutableToDateTimeTransformer();

        $this->assertNull($transformer->reverseTransform(null));
    }

    public function testReverseTransformFail()
    {
        $this->expectException('Symfony\Component\Form\Exception\TransformationFailedException');
        $this->expectExceptionMessage('Expected a \DateTime.');
        $transformer = new DateTimeImmutableToDateTimeTransformer();
        $transformer->reverseTransform(new \DateTimeImmutable());
    }
}
