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
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\DataTransformer\BooleanToStringTransformer;

class BooleanToStringTransformerTest extends TestCase
{
    private const TRUE_VALUE = '1';

    /**
     * @var BooleanToStringTransformer
     */
    protected $transformer;

    protected function setUp(): void
    {
        $this->transformer = new BooleanToStringTransformer(self::TRUE_VALUE);
    }

    protected function tearDown(): void
    {
        $this->transformer = null;
    }

    public function testTransform()
    {
        self::assertEquals(self::TRUE_VALUE, $this->transformer->transform(true));
        self::assertNull($this->transformer->transform(false));
    }

    // https://github.com/symfony/symfony/issues/8989
    public function testTransformAcceptsNull()
    {
        self::assertNull($this->transformer->transform(null));
    }

    public function testTransformFailsIfString()
    {
        self::expectException(TransformationFailedException::class);
        $this->transformer->transform('1');
    }

    public function testReverseTransformFailsIfInteger()
    {
        self::expectException(TransformationFailedException::class);
        $this->transformer->reverseTransform(1);
    }

    public function testReverseTransform()
    {
        self::assertTrue($this->transformer->reverseTransform(self::TRUE_VALUE));
        self::assertTrue($this->transformer->reverseTransform('foobar'));
        self::assertTrue($this->transformer->reverseTransform(''));
        self::assertFalse($this->transformer->reverseTransform(null));
    }

    public function testCustomFalseValues()
    {
        $customFalseTransformer = new BooleanToStringTransformer(self::TRUE_VALUE, ['0', 'myFalse', true]);
        self::assertFalse($customFalseTransformer->reverseTransform('myFalse'));
        self::assertFalse($customFalseTransformer->reverseTransform('0'));
        self::assertFalse($customFalseTransformer->reverseTransform(true));
    }

    public function testTrueValueContainedInFalseValues()
    {
        self::expectException(InvalidArgumentException::class);
        new BooleanToStringTransformer('0', [null, '0']);
    }

    public function testBeStrictOnTrueInFalseValueCheck()
    {
        $transformer = new BooleanToStringTransformer('0', [null, false]);
        self::assertInstanceOf(BooleanToStringTransformer::class, $transformer);
    }
}
