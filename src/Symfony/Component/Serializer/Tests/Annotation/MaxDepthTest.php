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
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends TestCase
{
    /**
     * @testWith    [-4]
     *              [0]
     */
    public function testNotAnIntMaxDepthParameter(int $value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter given to "Symfony\Component\Serializer\Attribute\MaxDepth" must be a positive integer.');
        new MaxDepth($value);
    }

    public function testMaxDepthParameters()
    {
        $maxDepth = new MaxDepth(3);
        $this->assertEquals(3, $maxDepth->getMaxDepth());
    }
}
