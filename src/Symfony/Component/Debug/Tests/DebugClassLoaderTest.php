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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\DebugClassLoader;

class DebugClassLoaderTest extends TestCase
{
    /**
     * @var int Error reporting level before running tests
     */
    private $errorReporting;

    private $loader;

    protected function setUp()
    {
        $this->errorReporting = error_reporting(E_ALL);
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

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage boo
     */
    public function testThrowingClass()
    {
        try {
            class_exists(__NAMESPACE__.'\Fixtures\Throwing');
            $this->fail('Exception expected');
        } catch (\Exception $e) {
            $this->assertSame('boo', $e->getMessage());
        }

        // the second call also should throw
        class_exists(__NAMESPACE__.'\Fixtures\Throwing');
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
     * @expectedExceptionMessage Case mismatch between class and real file names
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

    /**
     * @dataProvider provideDeprecatedSuper
     */
    public function testDeprecatedSuper($class, $super, $type)
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\'.$class, true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = array(
            'type' => E_USER_DEPRECATED,
            'message' => 'The "Test\Symfony\Component\Debug\Tests\\'.$class.'" class '.$type.' "Symfony\Component\Debug\Tests\Fixtures\\'.$super.'" that is deprecated but this is a test deprecation notice.',
        );

        $this->assertSame($xError, $lastError);
    }

    public function provideDeprecatedSuper()
    {
        return array(
            array('DeprecatedInterfaceClass', 'DeprecatedInterface', 'implements'),
            array('DeprecatedParentClass', 'DeprecatedClass', 'extends'),
        );
    }

    public function testInterfaceExtendsDeprecatedInterface()
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        class_exists('Test\\'.__NAMESPACE__.'\\NonDeprecatedInterfaceClass', true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = array(
            'type' => E_USER_NOTICE,
            'message' => '',
        );

        $this->assertSame($xError, $lastError);
    }

    public function testDeprecatedSuperInSameNamespace()
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        class_exists('Symfony\Bridge\Debug\Tests\Fixtures\ExtendsDeprecatedParent', true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = array(
            'type' => E_USER_NOTICE,
            'message' => '',
        );

        $this->assertSame($xError, $lastError);
    }

    public function testExtendedFinalClass()
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsFinalClass', true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = array(
            'type' => E_USER_DEPRECATED,
            'message' => 'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass" class is considered final since version 3.3. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass".',
        );

        $this->assertSame($xError, $lastError);
    }

    public function testExtendedFinalMethod()
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        class_exists(__NAMESPACE__.'\\Fixtures\\ExtendedFinalMethod', true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = array(
            'type' => E_USER_DEPRECATED,
            'message' => 'The "Symfony\Component\Debug\Tests\Fixtures\FinalMethod::finalMethod()" method is considered final since version 3.3. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\Debug\Tests\Fixtures\ExtendedFinalMethod".',
        );

        $this->assertSame($xError, $lastError);
    }

    public function testExtendedDeprecatedMethodDoesntTriggerAnyNotice()
    {
        set_error_handler(function () { return false; });
        $e = error_reporting(0);
        trigger_error('', E_USER_NOTICE);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsAnnotatedClass', true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $this->assertSame(array('type' => E_USER_NOTICE, 'message' => ''), $lastError);
    }

    public function testInternalsUse()
    {
        $deprecations = array();
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsInternals', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame($deprecations, array(
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalClass" class is considered internal since version 3.4. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalInterface" interface is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalTrait" trait is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternals".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalTrait2::internalMethod()" method is considered internal since version 3.4. It may change without further notice. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsInternals".',
        ));
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
        $fixtureDir = __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR;

        if (__NAMESPACE__.'\TestingUnsilencing' === $class) {
            eval('-- parse error --');
        } elseif (__NAMESPACE__.'\TestingStacking' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingStacking { function foo() {} }');
        } elseif (__NAMESPACE__.'\TestingCaseMismatch' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingCaseMisMatch {}');
        } elseif (__NAMESPACE__.'\Fixtures\Psr4CaseMismatch' === $class) {
            return $fixtureDir.'psr4'.DIRECTORY_SEPARATOR.'Psr4CaseMismatch.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0' === $class) {
            return $fixtureDir.'reallyNotPsr0.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0bis' === $class) {
            return $fixtureDir.'notPsr0Bis.php';
        } elseif ('Symfony\Bridge\Debug\Tests\Fixtures\ExtendsDeprecatedParent' === $class) {
            eval('namespace Symfony\Bridge\Debug\Tests\Fixtures; class ExtendsDeprecatedParent extends \\'.__NAMESPACE__.'\Fixtures\DeprecatedClass {}');
        } elseif ('Test\\'.__NAMESPACE__.'\DeprecatedParentClass' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class DeprecatedParentClass extends \\'.__NAMESPACE__.'\Fixtures\DeprecatedClass {}');
        } elseif ('Test\\'.__NAMESPACE__.'\DeprecatedInterfaceClass' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class DeprecatedInterfaceClass implements \\'.__NAMESPACE__.'\Fixtures\DeprecatedInterface {}');
        } elseif ('Test\\'.__NAMESPACE__.'\NonDeprecatedInterfaceClass' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class NonDeprecatedInterfaceClass implements \\'.__NAMESPACE__.'\Fixtures\NonDeprecatedInterface {}');
        } elseif ('Test\\'.__NAMESPACE__.'\Float' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class Float {}');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsFinalClass' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsFinalClass extends \\'.__NAMESPACE__.'\Fixtures\FinalClass {}');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsAnnotatedClass' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsAnnotatedClass extends \\'.__NAMESPACE__.'\Fixtures\AnnotatedClass {
                public function deprecatedMethod() { }
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsInternals' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsInternals extends ExtendsInternalsParent {
                use \\'.__NAMESPACE__.'\Fixtures\InternalTrait;

                public function internalMethod() { }
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsInternalsParent' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsInternalsParent extends \\'.__NAMESPACE__.'\Fixtures\InternalClass implements \\'.__NAMESPACE__.'\Fixtures\InternalInterface { }');
        }
    }
}
