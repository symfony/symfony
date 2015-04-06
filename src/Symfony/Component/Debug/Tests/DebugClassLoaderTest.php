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

use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\Exception\ContextErrorException;

class DebugClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var int Error reporting level before running tests.
     */
    private $errorReporting;

    private $loader;

    protected function setUp()
    {
        $this->errorReporting = error_reporting(E_ALL | E_STRICT);
        $this->loader = new ClassLoader();
        spl_autoload_register(array($this->loader, 'loadClass'), true, true);
        DebugClassLoader::enable();
    }

    protected function tearDown()
    {
        DebugClassLoader::disable();
        spl_autoload_unregister(array($this->loader, 'loadClass'));
        error_reporting($this->errorReporting);
    }

    public function testIdempotence()
    {
        DebugClassLoader::enable();

        $functions = spl_autoload_functions();
        foreach ($functions as $function) {
            if (is_array($function) && $function[0] instanceof DebugClassLoader) {
                $reflClass = new \ReflectionClass($function[0]);
                $reflProp = $reflClass->getProperty('classLoader');
                $reflProp->setAccessible(true);

                $this->assertNotInstanceOf('Symfony\Component\Debug\DebugClassLoader', $reflProp->getValue($function[0]));

                return;
            }
        }

        $this->fail('DebugClassLoader did not register');
    }

    public function testUnsilencing()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('PHP7 throws exceptions, unsilencing is not required anymore.');
        }

        ob_start();

        $this->iniSet('log_errors', 0);
        $this->iniSet('display_errors', 1);

        // See below: this will fail with parse error
        // but this should not be @-silenced.
        @class_exists(__NAMESPACE__.'\TestingUnsilencing', true);

        $output = ob_get_clean();

        $this->assertStringMatchesFormat('%aParse error%a', $output);
    }

    public function testStacking()
    {
        // the ContextErrorException must not be loaded to test the workaround
        // for https://bugs.php.net/65322.
        if (class_exists('Symfony\Component\Debug\Exception\ContextErrorException', false)) {
            $this->markTestSkipped('The ContextErrorException class is already loaded.');
        }

        ErrorHandler::register();

        try {
            // Trigger autoloading + E_STRICT at compile time
            // which in turn triggers $errorHandler->handle()
            // that again triggers autoloading for ContextErrorException.
            // Error stacking works around the bug above and everything is fine.

            eval('
                namespace '.__NAMESPACE__.';
                class ChildTestingStacking extends TestingStacking { function foo($bar) {} }
            ');
            $this->fail('ContextErrorException expected');
        } catch (\ErrorException $exception) {
            // if an exception is thrown, the test passed
            restore_error_handler();
            restore_exception_handler();
            $this->assertEquals(E_STRICT, $exception->getSeverity());
            $this->assertStringStartsWith(__FILE__, $exception->getFile());
            $this->assertRegexp('/^Runtime Notice: Declaration/', $exception->getMessage());
        } catch (\Exception $exception) {
            restore_error_handler();
            restore_exception_handler();

            throw $exception;
        }
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNameCaseMismatch()
    {
        class_exists(__NAMESPACE__.'\TestingCaseMismatch', true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFileCaseMismatch()
    {
        if (!file_exists(__DIR__.'/Fixtures/CaseMismatch.php')) {
            $this->markTestSkipped('Can only be run on case insensitive filesystems');
        }

        class_exists(__NAMESPACE__.'\Fixtures\CaseMismatch', true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testPsr4CaseMismatch()
    {
        class_exists(__NAMESPACE__.'\Fixtures\Psr4CaseMismatch', true);
    }

    public function testNotPsr0()
    {
        $this->assertTrue(class_exists(__NAMESPACE__.'\Fixtures\NotPSR0', true));
    }

    public function testNotPsr0Bis()
    {
        $this->assertTrue(class_exists(__NAMESPACE__.'\Fixtures\NotPSR0bis', true));
    }

    public function testClassAlias()
    {
        $this->assertTrue(class_exists(__NAMESPACE__.'\Fixtures\ClassAlias', true));
    }
}

class ClassLoader
{
    public function loadClass($class)
    {
    }

    public function getClassMap()
    {
        return array(__NAMESPACE__.'\Fixtures\NotPSR0bis' => __DIR__.'/Fixtures/notPsr0Bis.php');
    }

    public function findFile($class)
    {
        if (__NAMESPACE__.'\TestingUnsilencing' === $class) {
            eval('-- parse error --');
        } elseif (__NAMESPACE__.'\TestingStacking' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingStacking { function foo() {} }');
        } elseif (__NAMESPACE__.'\TestingCaseMismatch' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingCaseMisMatch {}');
        } elseif (__NAMESPACE__.'\Fixtures\CaseMismatch' === $class) {
            return __DIR__.'/Fixtures/CaseMismatch.php';
        } elseif (__NAMESPACE__.'\Fixtures\Psr4CaseMismatch' === $class) {
            return __DIR__.'/Fixtures/psr4/Psr4CaseMismatch.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0' === $class) {
            return __DIR__.'/Fixtures/reallyNotPsr0.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0bis' === $class) {
            return __DIR__.'/Fixtures/notPsr0Bis.php';
        }
    }
}
