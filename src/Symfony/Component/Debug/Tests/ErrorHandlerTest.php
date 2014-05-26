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
use Symfony\Component\Debug\Exception\ContextErrorException;

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

    public function testNotice()
    {
        ErrorHandler::register();

        try {
            self::triggerNotice($this);
            $this->fail('ContextErrorException expected');
        } catch (ContextErrorException $exception) {
            // if an exception is thrown, the test passed
            restore_error_handler();
            $this->assertEquals(E_NOTICE, $exception->getSeverity());
            $this->assertEquals(__FILE__, $exception->getFile());
            $this->assertRegexp('/^Notice: Undefined variable: (foo|bar)/', $exception->getMessage());
            $this->assertArrayHasKey('foobar', $exception->getContext());

            $trace = $exception->getTrace();
            $this->assertEquals(__FILE__, $trace[0]['file']);
            $this->assertEquals('Symfony\Component\Debug\ErrorHandler', $trace[0]['class']);
            $this->assertEquals('handle', $trace[0]['function']);
            $this->assertEquals('->', $trace[0]['type']);

            $this->assertEquals(__FILE__, $trace[1]['file']);
            $this->assertEquals(__CLASS__, $trace[1]['class']);
            $this->assertEquals('triggerNotice', $trace[1]['function']);
            $this->assertEquals('::', $trace[1]['type']);

            $this->assertEquals(__FILE__, $trace[1]['file']);
            $this->assertEquals(__CLASS__, $trace[2]['class']);
            $this->assertEquals(__FUNCTION__, $trace[2]['function']);
            $this->assertEquals('->', $trace[2]['type']);
        } catch (\Exception $e) {
            restore_error_handler();

            throw $e;
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
        try {
            $handler = ErrorHandler::register(3);

            $level = new \ReflectionProperty($handler, 'level');
            $level->setAccessible(true);

            $this->assertEquals(3, $level->getValue($handler));

            restore_error_handler();
        } catch (\Exception $e) {
            restore_error_handler();

            throw $e;
        }
    }

    public function testHandle()
    {
        try {
            $handler = ErrorHandler::register(0);
            $this->assertFalse($handler->handle(0, 'foo', 'foo.php', 12, array()));

            restore_error_handler();

            $handler = ErrorHandler::register(3);
            $this->assertFalse($handler->handle(4, 'foo', 'foo.php', 12, array()));

            restore_error_handler();

            $handler = ErrorHandler::register(3);
            try {
                $handler->handle(4, 'foo', 'foo.php', 12, array());
            } catch (\ErrorException $e) {
                $this->assertSame('Parse Error: foo in foo.php line 12', $e->getMessage());
                $this->assertSame(4, $e->getSeverity());
                $this->assertSame('foo.php', $e->getFile());
                $this->assertSame(12, $e->getLine());
            }

            restore_error_handler();

            $handler = ErrorHandler::register(E_USER_DEPRECATED);
            $this->assertFalse($handler->handle(E_USER_DEPRECATED, 'foo', 'foo.php', 12, array()));

            restore_error_handler();

            $handler = ErrorHandler::register(E_DEPRECATED);
            $this->assertFalse($handler->handle(E_DEPRECATED, 'foo', 'foo.php', 12, array()));

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
            $this->assertTrue($handler->handle(E_USER_DEPRECATED, 'foo', 'foo.php', 12, array()));

            restore_error_handler();

            $logger = $this->getMock('Psr\Log\LoggerInterface');

            $that = $this;
            $logArgCheck = function ($level, $message, $context) use ($that) {
                $that->assertEquals('Undefined variable: undefVar', $message);
                $that->assertArrayHasKey('type', $context);
                $that->assertEquals($context['type'], E_NOTICE);
            };

            $logger
                ->expects($this->once())
                ->method('log')
                ->will($this->returnCallback($logArgCheck))
            ;

            $handler = ErrorHandler::register(E_NOTICE);
            $handler->setLogger($logger, 'scream');
            unset($undefVar);
            @$undefVar++;

            restore_error_handler();
        } catch (\Exception $e) {
            restore_error_handler();

            throw $e;
        }
    }
}
