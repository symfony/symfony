<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorEnhancer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Error\NonObjectMethodError;
use Symfony\Component\ErrorHandler\ErrorEnhancer\NonObjectMethodErrorEnhancer;

class NonObjectMethodErrorEnhancerTest extends TestCase
{
    public $nullProperty = null;

    public static function method($return)
    {
        return $return;
    }

    /**
     * @dataProvider providePropertyData
     */
    public function testPropertyEnhance($property, string $originalMessage, string $enhancedMessage)
    {
        $this->nullProperty = $property;

        $generatedError = null;
        try {
            $expectedLine = __LINE__ + 1;
            $this->nullProperty->callMethod();
        } catch (\Throwable $exception) {
            $generatedError = $exception;
        }

        $this->checkAssertions($generatedError, $originalMessage, $enhancedMessage, $expectedLine);
    }

    public function providePropertyData()
    {
        return [
            [
                null,
                'Call to a member function callMethod() on null',
                'Attempted to call method "callMethod()" of expression "$this->nullProperty", which contains a non object, but a null.',
            ],
            [
                [],
                'Call to a member function callMethod() on array',
                'Attempted to call method "callMethod()" of expression "$this->nullProperty", which contains a non object, but an array.',
            ],
        ];
    }

    /**
     * @dataProvider provideSelfMethodData
     */
    public function testSelfMethodEnhance($methodReturn, string $originalMessage, string $enhancedMessage)
    {
        $generatedError = null;
        try {
            $expectedLine = __LINE__ + 1;
            self::method($methodReturn)->callMethod();
        } catch (\Throwable $exception) {
            $generatedError = $exception;
        }

        $this->checkAssertions($generatedError, $originalMessage, $enhancedMessage, $expectedLine);
    }

    public function provideSelfMethodData()
    {
        return [
            [
                null,
                'Call to a member function callMethod() on null',
                'Attempted to call method "callMethod()" of expression "self::method($methodReturn)", which contains a non object, but a null.',
            ],
            [
                [],
                'Call to a member function callMethod() on array',
                'Attempted to call method "callMethod()" of expression "self::method($methodReturn)", which contains a non object, but an array.',
            ],
        ];
    }

    /**
     * @dataProvider provideStaticMethodData
     */
    public function testStaticMethodEnhance($methodReturn, string $originalMessage, string $enhancedMessage)
    {
        $generatedError = null;
        try {
            $expectedLine = __LINE__ + 1;
            static::method($methodReturn)->callMethod();
        } catch (\Throwable $exception) {
            $generatedError = $exception;
        }

        $this->checkAssertions($generatedError, $originalMessage, $enhancedMessage, $expectedLine);
    }

    public function provideStaticMethodData()
    {
        return [
            [
                null,
                'Call to a member function callMethod() on null',
                'Attempted to call method "callMethod()" of expression "static::method($methodReturn)", which contains a non object, but a null.',
            ],
            [
                [],
                'Call to a member function callMethod() on array',
                'Attempted to call method "callMethod()" of expression "static::method($methodReturn)", which contains a non object, but an array.',
            ],
        ];
    }

    /**
     * @dataProvider provideDefaultData
     */
    public function testDefaultEnhance($methodReturn, string $originalMessage)
    {
        $generatedError = null;
        try {
            static::method($methodReturn)
                ->callMethod();
        } catch (\Throwable $exception) {
            $generatedError = $exception;
        }

        $this->assertSame($originalMessage, $generatedError->getMessage());

        $enhancer = new NonObjectMethodErrorEnhancer();
        $error = $enhancer->enhance($generatedError);

        $this->assertNull($error);
    }

    public function provideDefaultData()
    {
        return [
            [
                null,
                'Call to a member function callMethod() on null',
            ],
            [
                [],
                'Call to a member function callMethod() on array',
            ],
        ];
    }

    private function checkAssertions(\Throwable $generatedError, string $originalMessage, string $enhancedMessage, int $expectedLine)
    {
        $this->assertSame($originalMessage, $generatedError->getMessage());

        $enhancer = new NonObjectMethodErrorEnhancer();
        $error = $enhancer->enhance($generatedError);

        $this->assertInstanceOf(NonObjectMethodError::class, $error);
        $this->assertSame($enhancedMessage, $error->getMessage());
        $this->assertSame(realpath(__FILE__), $error->getFile());
        $this->assertSame($expectedLine, $error->getLine());
    }
}
