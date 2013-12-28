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
use Symfony\Component\Debug\Exception\DummyException;

/**
 * ErrorHandlerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var int Error reporting level before running tests.
     */
    protected $errorReporting;

    /**
     * @var string Display errors setting before running tests.
     */
    protected $displayErrors;

    public function setUp()
    {
        $this->errorReporting = error_reporting(E_ALL | E_STRICT);
        $this->displayErrors = ini_get('display_errors');
        ini_set('display_errors', '1');
    }

    public function tearDown()
    {
        ini_set('display_errors', $this->displayErrors);
        error_reporting($this->errorReporting);
    }

    public function testCompileTimeError()
    {
        // the ContextErrorException must not be loaded to test the workaround
        // for https://bugs.php.net/bug.php?id=65322.
        if (class_exists('Symfony\Component\Debug\Exception\ContextErrorException', false)) {
            $this->markTestSkipped('The ContextErrorException class is already loaded.');
        }

        $exceptionHandler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('handle'));

        // the following code forces some PHPUnit classes to be loaded
        // so that they will be available in the exception handler
        // as they won't be autoloaded by PHP
        class_exists('PHPUnit_Framework_MockObject_Invocation_Object');
        $this->assertInstanceOf('stdClass', new \stdClass());
        $this->assertEquals(1, 1);
        $this->assertStringStartsWith('foo', 'foobar');
        $this->assertArrayHasKey('bar', array('bar' => 'foo'));

        $that = $this;
        $exceptionCheck = function ($exception) use ($that) {
            $that->assertInstanceOf('Symfony\Component\Debug\Exception\ContextErrorException', $exception);
            $that->assertEquals(E_STRICT, $exception->getSeverity());
            $that->assertEquals(2, $exception->getLine());
            $that->assertStringStartsWith('Runtime Notice: Declaration of _CompileTimeError::foo() should be compatible with', $exception->getMessage());
            $that->assertArrayHasKey('bar', $exception->getContext());
        };

        $exceptionHandler->expects($this->once())
            ->method('handle')
            ->will($this->returnCallback($exceptionCheck))
        ;

        ErrorHandler::register();
        set_exception_handler(array($exceptionHandler, 'handle'));

        // dummy variable to check for in error handler.
        $bar = 123;

        // trigger compile time error
        try {
            eval(<<<'PHP'
class _BaseCompileTimeError { function foo() {} }
class _CompileTimeError extends _BaseCompileTimeError { function foo($invalid) {} }
PHP
            );
        } catch (DummyException $e) {
            // if an exception is thrown, the test passed
        }

        restore_error_handler();
        restore_exception_handler();
    }

    public function testNotice()
    {
        $exceptionHandler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('handle'));
        set_exception_handler(array($exceptionHandler, 'handle'));

        $that = $this;
        $exceptionCheck = function ($exception) use ($that) {
            $that->assertInstanceOf('Symfony\Component\Debug\Exception\ContextErrorException', $exception);
            $that->assertEquals(E_NOTICE, $exception->getSeverity());
            $that->assertEquals(__LINE__ + 40, $exception->getLine());
            $that->assertEquals(__FILE__, $exception->getFile());
            $that->assertRegexp('/^Notice: Undefined variable: (foo|bar)/', $exception->getMessage());
            $that->assertArrayHasKey('foobar', $exception->getContext());

            $trace = $exception->getTrace();
            $that->assertEquals(__FILE__, $trace[0]['file']);
            $that->assertEquals('Symfony\Component\Debug\ErrorHandler', $trace[0]['class']);
            $that->assertEquals('handle', $trace[0]['function']);
            $that->assertEquals('->', $trace[0]['type']);

            $that->assertEquals(__FILE__, $trace[1]['file']);
            $that->assertEquals(__CLASS__, $trace[1]['class']);
            $that->assertEquals('triggerNotice', $trace[1]['function']);
            $that->assertEquals('::', $trace[1]['type']);

            $that->assertEquals(__CLASS__, $trace[2]['class']);
            $that->assertEquals('testNotice', $trace[2]['function']);
            $that->assertEquals('->', $trace[2]['type']);
        };

        $exceptionHandler->expects($this->once())
            ->method('handle')
            ->will($this->returnCallback($exceptionCheck));
        ErrorHandler::register();

        try {
            self::triggerNotice($this);
        } catch (DummyException $e) {
            // if an exception is thrown, the test passed
        }

        restore_error_handler();
    }

    // dummy function to test trace in error handler.
    private static function triggerNotice($that)
    {
        // dummy variable to check for in error handler.
        $foobar = 123;
        $that->assertSame('', $foo.$foo.$bar);
    }

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
        $warnArgCheck = function ($message, $context) use ($that) {
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
}
