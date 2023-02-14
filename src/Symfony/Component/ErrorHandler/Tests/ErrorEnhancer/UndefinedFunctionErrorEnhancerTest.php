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
use Symfony\Component\ErrorHandler\Error\UndefinedFunctionError;
use Symfony\Component\ErrorHandler\ErrorEnhancer\UndefinedFunctionErrorEnhancer;

class UndefinedFunctionErrorEnhancerTest extends TestCase
{
    /**
     * @dataProvider provideUndefinedFunctionData
     */
    public function testEnhance(string $originalMessage, string $enhancedMessage)
    {
        $enhancer = new UndefinedFunctionErrorEnhancer();

        $expectedLine = __LINE__ + 1;
        $error = $enhancer->enhance(new \Error($originalMessage));

        $this->assertInstanceOf(UndefinedFunctionError::class, $error);
        // class names are case insensitive and PHP do not return the same
        $this->assertSame(strtolower($enhancedMessage), strtolower($error->getMessage()));
        $this->assertSame(realpath(__FILE__), $error->getFile());
        $this->assertSame($expectedLine, $error->getLine());
    }

    public static function provideUndefinedFunctionData()
    {
        return [
            [
                'Call to undefined function test_namespaced_function()',
                "Attempted to call function \"test_namespaced_function\" from the global namespace.\nDid you mean to call \"\\symfony\\component\\errorhandler\\tests\\errorenhancer\\test_namespaced_function\"?",
            ],
            [
                'Call to undefined function Foo\\Bar\\Baz\\test_namespaced_function()',
                "Attempted to call function \"test_namespaced_function\" from namespace \"Foo\\Bar\\Baz\".\nDid you mean to call \"\\symfony\\component\\errorhandler\\tests\\errorenhancer\\test_namespaced_function\"?",
            ],
            [
                'Call to undefined function foo()',
                'Attempted to call function "foo" from the global namespace.',
            ],
            [
                'Call to undefined function Foo\\Bar\\Baz\\foo()',
                'Attempted to call function "foo" from namespace "Foo\Bar\Baz".',
            ],
        ];
    }
}

function test_namespaced_function()
{
}
