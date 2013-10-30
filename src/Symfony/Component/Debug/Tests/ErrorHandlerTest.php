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

/**
 * ErrorHandlerTest
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testCompileTimeError()
    {
        // the ContextErrorException must not be loaded for this test to work
        if (class_exists('Symfony\Component\Debug\Exception\ContextErrorException', false)) {
            $this->markTestSkipped('The ContextErrorException class is already loaded.');
        }

        $handler = ErrorHandler::register(E_ALL | E_STRICT);
        $displayErrors = ini_get('display_errors');
        ini_set('display_errors', '1');

        try {
            // trigger compile time error
            eval(<<<'PHP'
class _BaseCompileTimeError { function foo() {} }
class _CompileTimeError extends _BaseCompileTimeError { function foo($invalid) {} }
PHP
            );
        } catch (\Exception $e) {
            // if an exception is thrown, the test passed
        }

        ini_set('display_errors', $displayErrors);
        restore_error_handler();
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
}
