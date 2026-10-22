<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutowireStack;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class AutowireStackTest extends TestCase
{
    public function testEmptyStack()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('#[AutowireStack] attribute cannot declare an empty stack.');

        new AutowireStack([]);
    }

    #[DataProvider('provideInvalidFrames')]
    public function testInvalidFrame(mixed $frame)
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid frame "decorator" in #[AutowireStack]: expected a class name or an AutowireInline instance');

        new AutowireStack(['decorator' => $frame]);
    }

    public static function provideInvalidFrames(): iterable
    {
        yield [null];
        yield [123];
        yield [false];
        yield [[]];
        yield [new \stdClass()];
    }

    public function testNestedStack()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('#[AutowireStack] attribute cannot contain another stack.');

        new AutowireStack([new AutowireStack([\stdClass::class])]);
    }
}
