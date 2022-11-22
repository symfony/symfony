<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\InvalidParameterTypeException;

final class InvalidParameterTypeExceptionTest extends TestCase
{
    /**
     * @dataProvider provideReflectionParameters
     */
    public function testExceptionMessage(\ReflectionParameter $parameter, string $expectedMessage)
    {
        $exception = new InvalidParameterTypeException('my_service', 'int', $parameter);

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    public static function provideReflectionParameters(): iterable
    {
        yield 'static method' => [
            new \ReflectionParameter([MyClass::class, 'doSomething'], 0),
            'Invalid definition for service "my_service": argument 1 of "Symfony\Component\DependencyInjection\Tests\Exception\MyClass::doSomething()" accepts "array", "int" passed.',
        ];

        yield 'function' => [
            new \ReflectionParameter(__NAMESPACE__.'\\myFunction', 0),
            'Invalid definition for service "my_service": argument 1 of "Symfony\Component\DependencyInjection\Tests\Exception\myFunction()" accepts "array", "int" passed.',
        ];
    }
}

class MyClass
{
    public static function doSomething(array $arguments): void
    {
    }
}

function myFunction(array $arguments): void
{
}
