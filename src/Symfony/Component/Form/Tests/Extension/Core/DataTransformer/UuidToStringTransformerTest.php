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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\UuidToStringTransformer;
use Symfony\Component\Uid\Uuid;

class UuidToStringTransformerTest extends TestCase
{
    public function provideValidUuid()
    {
        return [
            ['123e4567-e89b-12d3-a456-426655440000', new Uuid('123e4567-e89b-12d3-a456-426655440000')],
        ];
    }

    /**
     * @dataProvider provideValidUuid
     */
    public function testTransform($output, $input)
    {
        $transformer = new UuidToStringTransformer();

        $input = new Uuid($input);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new UuidToStringTransformer();

        $this->assertNull($transformer->transform(null));
    }

    public function testTransformExpectsUuid()
    {
        $transformer = new UuidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->transform('1234');
    }

    /**
     * @dataProvider provideValidUuid
     */
    public function testReverseTransform($input, $output)
    {
        $reverseTransformer = new UuidToStringTransformer();

        $output = new Uuid($output);

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $reverseTransformer = new UuidToStringTransformer();

        $this->assertNull($reverseTransformer->reverseTransform(''));
    }

    public function testReverseTransformExpectsString()
    {
        $reverseTransformer = new UuidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform(1234);
    }

    public function testReverseTransformExpectsValidUuidString()
    {
        $reverseTransformer = new UuidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform('1234');
    }
}
