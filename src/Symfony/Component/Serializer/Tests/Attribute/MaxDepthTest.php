<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Attribute;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MaxDepthTest extends TestCase
{
    #[TestWith([-4])]
    public function testNotAnIntMaxDepthParameter(int $value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter given to "Symfony\Component\Serializer\Attribute\MaxDepth" must be a non-negative integer.');
        new MaxDepth($value);
    }

    #[TestWith([0])]
    #[TestWith([3])]
    public function testMaxDepthParameters(int $value)
    {
        $maxDepth = new MaxDepth($value);
        $this->assertSame($value, $maxDepth->maxDepth);
    }
}
