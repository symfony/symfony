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
use Symfony\Component\Form\Extension\Core\DataTransformer\UlidToStringTransformer;
use Symfony\Component\Uid\Ulid;

class UlidToStringTransformerTest extends TestCase
{
    public static function provideValidUlid()
    {
        return [
            ['01D85PP1982GF6KTVFHQ7W78FB', new Ulid('01d85pp1982gf6ktvfhq7w78fb')],
        ];
    }

    /**
     * @dataProvider provideValidUlid
     */
    public function testTransform($output, $input)
    {
        $transformer = new UlidToStringTransformer();

        $input = new Ulid($input);

        $this->assertEquals($output, $transformer->transform($input));
    }

    public function testTransformEmpty()
    {
        $transformer = new UlidToStringTransformer();

        $this->assertNull($transformer->transform(null));
    }

    public function testTransformExpectsUlid()
    {
        $transformer = new UlidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $transformer->transform('1234');
    }

    /**
     * @dataProvider provideValidUlid
     */
    public function testReverseTransform($input, $output)
    {
        $reverseTransformer = new UlidToStringTransformer();

        $output = new Ulid($output);

        $this->assertEquals($output, $reverseTransformer->reverseTransform($input));
    }

    public function testReverseTransformEmpty()
    {
        $reverseTransformer = new UlidToStringTransformer();

        $this->assertNull($reverseTransformer->reverseTransform(''));
    }

    public function testReverseTransformExpectsString()
    {
        $reverseTransformer = new UlidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform(1234);
    }

    public function testReverseTransformExpectsValidUlidString()
    {
        $reverseTransformer = new UlidToStringTransformer();

        $this->expectException(TransformationFailedException::class);

        $reverseTransformer->reverseTransform('1234');
    }
}
