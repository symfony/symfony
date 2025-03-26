<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;
use Symfony\Component\JsonStreamer\Exception\LogicException;

class ValueTransformerTest extends TestCase
{
    public function testCannotCreateWithoutAnything()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('#[ValueTransformer] attribute must declare either $streamToNative or $nativeToStream.');

        new ValueTransformer();
    }
}
