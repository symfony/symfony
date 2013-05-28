<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests;

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\Exception\ClassNotFoundException;
use Symfony\Component\Debug\ExceptionHandler;

/**
 * ErrorHandlerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{

    public function testConstruct()
    {
        $handler = ErrorHandler::register(3);

        $level = new \ReflectionProperty($handler, 'level');
        $level->setAccessible(true);

        $this->assertEquals(3, $level->getValue($handler));

        restore_error_handler();
    }

    public function testHandle()
    {
        $handler = ErrorHandler::register(0);
        $this->assertFalse($handler->handle(0, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $handler = ErrorHandler::register(3);
        $this->assertFalse($handler->handle(4, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $handler = ErrorHandler::register(3);
        try {
            $handler->handle(111, 'foo', 'foo.php', 12, 'foo');
        } catch (\ErrorException $e) {
            $this->assertSame('111: foo in foo.php line 12', $e->getMessage());
            $this->assertSame(111, $e->getSeverity());
            $this->assertSame('foo.php', $e->getFile());
            $this->assertSame(12, $e->getLine());
        }

        restore_error_handler();

        $handler = ErrorHandler::register(E_USER_DEPRECATED);
        $this->assertTrue($handler->handle(E_USER_DEPRECATED, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $handler = ErrorHandler::register(E_DEPRECATED);
        $this->assertTrue($handler->handle(E_DEPRECATED, 'foo', 'foo.php', 12, 'foo'));

        restore_error_handler();

        $logger = $this->getMock('Psr\Log\LoggerInterface');

        $that = $this;
        $warnArgCheck = function($message, $context) use ($that) {
            $that->assertEquals('foo', $message);
            $that->assertArrayHasKey('type', $context);
            $that->assertEquals($context['type'], ErrorHandler::TYPE_DEPRECATION);
            $that->assertArrayHasKey('stack', $context);
            $that->assertInternalType('array', $context['stack']);
        };

        $logger
            ->expects($this->once())
            ->method('warning')
            ->will($this->returnCallback($warnArgCheck))
        ;

        $handler = ErrorHandler::register(E_USER_DEPRECATED);
        $handler->setLogger($logger);
        $handler->handle(E_USER_DEPRECATED, 'foo', 'foo.php', 12, 'foo');

        restore_error_handler();
    }

    public function provideClassNotFoundData()
    {
        return array(
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'WhizBangFactory' not found",
                ),
                "Attempted to load class 'WhizBangFactory' from the global namespace in foo.php line 12. Did you forget a use statement for this class?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'Foo\\Bar\\WhizBangFactory' not found",
                ),
                "Attempted to load class 'WhizBangFactory' from namespace 'Foo\\Bar' in foo.php line 12. Do you need to 'use' it from another namespace?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'Request' not found",
                ),
                "Attempted to load class 'Request' from the global namespace in foo.php line 12. Did you forget a use statement for this class? Perhaps you need to add 'use Symfony\\Component\\HttpFoundation\\Request' at the top of this file?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'Foo\\Bar\\Request' not found",
                ),
                "Attempted to load class 'Request' from namespace 'Foo\\Bar' in foo.php line 12. Do you need to 'use' it from another namespace? Perhaps you need to add 'use Symfony\\Component\\HttpFoundation\\Request' at the top of this file?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'Response' not found",
                ),
                "Attempted to load class 'Response' from the global namespace in foo.php line 12. Did you forget a use statement for this class? Perhaps you need to add 'use Symfony\\Component\\HttpFoundation\\Response' at the top of this file?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Class 'Foo\\Bar\\Response' not found",
                ),
                "Attempted to load class 'Response' from namespace 'Foo\\Bar' in foo.php line 12. Do you need to 'use' it from another namespace? Perhaps you need to add 'use Symfony\\Component\\HttpFoundation\\Response' at the top of this file?",
            ),
        );
    }

    /**
     * @dataProvider provideClassNotFoundData
     */
    public function testClassNotFound($error, $translatedMessage)
    {
        $handler = ErrorHandler::register(3);

        $exceptionHandler = new MockExceptionHandler;
        set_exception_handler(array($exceptionHandler, 'handle'));

        $handler->handleFatalError($error);

        $this->assertNotNull($exceptionHandler->e);
        $this->assertSame($translatedMessage, $exceptionHandler->e->getMessage());
        $this->assertSame($error['type'], $exceptionHandler->e->getSeverity());
        $this->assertSame($error['file'], $exceptionHandler->e->getFile());
        $this->assertSame($error['line'], $exceptionHandler->e->getLine());

        restore_exception_handler();
        restore_error_handler();
    }

    public function provideUndefinedFunctionData()
    {
        return array(
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Call to undefined function test_namespaced_function()",
                ),
                "Attempted to call function 'test_namespaced_function' from the global namespace in foo.php line 12. Did you mean to call: '\\symfony\\component\\debug\\tests\\test_namespaced_function'?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Call to undefined function Foo\\Bar\\Baz\\test_namespaced_function()",
                ),
                "Attempted to call function 'test_namespaced_function' from namespace 'Foo\\Bar\\Baz' in foo.php line 12. Did you mean to call: '\\symfony\\component\\debug\\tests\\test_namespaced_function'?",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Call to undefined function foo()",
                ),
                "Attempted to call function 'foo' from the global namespace in foo.php line 12.",
            ),
            array(
                array(
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => "Call to undefined function Foo\\Bar\\Baz\\foo()",
                ),
                "Attempted to call function 'foo' from namespace 'Foo\Bar\Baz' in foo.php line 12.",
            ),
        );
    }

    /**
     * @dataProvider provideUndefinedFunctionData
     */
    public function testUndefinedFunction($error, $translatedMessage)
    {
        $handler = ErrorHandler::register(3);

        $exceptionHandler = new MockExceptionHandler;
        set_exception_handler(array($exceptionHandler, 'handle'));

        $handler->handleFatalError($error);

        $this->assertNotNull($exceptionHandler->e);
        $this->assertSame($translatedMessage, $exceptionHandler->e->getMessage());
        $this->assertSame($error['type'], $exceptionHandler->e->getSeverity());
        $this->assertSame($error['file'], $exceptionHandler->e->getFile());
        $this->assertSame($error['line'], $exceptionHandler->e->getLine());

        restore_exception_handler();
        restore_error_handler();
    }
}

function test_namespaced_function()
{
}
