<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testNotSetMaxDepthParameter()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" should be set.');
        new MaxDepth([]);
    }

    public function provideInvalidValues()
    {
        return [
            [''],
            ['foo'],
            ['1'],
            [0],
        ];
    }

    /**
     * @dataProvider provideInvalidValues
     */
    public function testNotAnIntMaxDepthParameter($value)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Parameter of annotation "Symfony\Component\Serializer\Annotation\MaxDepth" must be a positive integer.');
        new MaxDepth($value);
    }

    public function testMaxDepthParameters()
    {
        $maxDepth = new MaxDepth(3);
        self::assertEquals(3, $maxDepth->getMaxDepth());
    }

    /**
     * @group legacy
     */
    public function testMaxDepthParametersLegacy()
    {
        $this->expectDeprecation('Since symfony/serializer 5.3: Passing an array as first argument to "Symfony\Component\Serializer\Annotation\MaxDepth::__construct" is deprecated. Use named arguments instead.');

        $maxDepth = new MaxDepth(['value' => 3]);
        self::assertEquals(3, $maxDepth->getMaxDepth());
    }
}
