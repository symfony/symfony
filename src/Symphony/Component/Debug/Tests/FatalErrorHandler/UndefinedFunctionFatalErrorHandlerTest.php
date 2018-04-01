<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Debug\Tests\FatalErrorHandler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Debug\Exception\FatalErrorException;
use Symphony\Component\Debug\FatalErrorHandler\UndefinedFunctionFatalErrorHandler;

class UndefinedFunctionFatalErrorHandlerTest extends TestCase
{
    /**
     * @dataProvider provideUndefinedFunctionData
     */
    public function testUndefinedFunction($error, $translatedMessage)
    {
        $handler = new UndefinedFunctionFatalErrorHandler();
        $exception = $handler->handleError($error, new FatalErrorException('', 0, $error['type'], $error['file'], $error['line']));

        $this->assertInstanceOf('Symphony\Component\Debug\Exception\UndefinedFunctionException', $exception);
        // class names are case insensitive and PHP do not return the same
        $this->assertSame(strtolower($translatedMessage), strtolower($exception->getMessage()));
        $this->assertSame($error['type'], $exception->getSeverity());
        $this->assertSame($error['file'], $exception->getFile());
        $this->assertSame($error['line'], $exception->getLine());
    }

    public function provideUndefinedFunctionData()
    {
        return array(
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined function test_namespaced_function()',
                ),
                "Attempted to call function \"test_namespaced_function\" from the global namespace.\nDid you mean to call \"\\symphony\\component\\debug\\tests\\fatalerrorhandler\\test_namespaced_function\"?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined function Foo\\Bar\\Baz\\test_namespaced_function()',
                ),
                "Attempted to call function \"test_namespaced_function\" from namespace \"Foo\\Bar\\Baz\".\nDid you mean to call \"\\symphony\\component\\debug\\tests\\fatalerrorhandler\\test_namespaced_function\"?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined function foo()',
                ),
                'Attempted to call function "foo" from the global namespace.',
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Call to undefined function Foo\\Bar\\Baz\\foo()',
                ),
                'Attempted to call function "foo" from namespace "Foo\Bar\Baz".',
            ),
        );
    }
}

function test_namespaced_function()
{
}
