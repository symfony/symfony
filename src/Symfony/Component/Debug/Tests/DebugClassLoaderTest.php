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

    protected function setUp(): void
    {
        $this->errorReporting = error_reporting(E_ALL);
        $this->loader = new ClassLoader();
        spl_autoload_register([$this->loader, 'loadClass'], true, true);
        DebugClassLoader::enable();
    }

    protected function tearDown(): void
    {
        DebugClassLoader::disable();
        spl_autoload_unregister([$this->loader, 'loadClass']);
        error_reporting($this->errorReporting);
    }

    public function testIdempotence()
    {
        DebugClassLoader::enable();

        $functions = spl_autoload_functions();
        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof DebugClassLoader) {
                $reflClass = new \ReflectionClass($function[0]);
                $reflProp = $reflClass->getProperty('classLoader');
                $reflProp->setAccessible(true);

                $this->assertNotInstanceOf('Symfony\Component\Debug\DebugClassLoader', $reflProp->getValue($function[0]));

                return;
            }
        }

        $this->fail('DebugClassLoader did not register');
    }

    public function testThrowingClass()
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('boo');
        try {
            class_exists(__NAMESPACE__.'\Fixtures\Throwing');
            $this->fail('Exception expected');
        } catch (\Exception $e) {
            $this->assertSame('boo', $e->getMessage());
        }

        // the second call also should throw
        class_exists(__NAMESPACE__.'\Fixtures\Throwing');
    }

    public function testNameCaseMismatch()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Case mismatch between loaded and declared class names');
        class_exists(__NAMESPACE__.'\TestingCaseMismatch', true);
    }

    public function testFileCaseMismatch()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Case mismatch between class and real file names');
        if (!file_exists(__DIR__.'/Fixtures/CaseMismatch.php')) {
            $this->markTestSkipped('Can only be run on case insensitive filesystems');
        }

        class_exists(__NAMESPACE__.'\Fixtures\CaseMismatch', true);
    }

    public function testPsr4CaseMismatch()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Case mismatch between loaded and declared class names');
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

        $xError = [
            'type' => E_USER_DEPRECATED,
            'message' => 'The "Test\Symfony\Component\Debug\Tests\\'.$class.'" class '.$type.' "Symfony\Component\Debug\Tests\Fixtures\\'.$super.'" that is deprecated but this is a test deprecation notice.',
        ];

        $this->assertSame($xError, $lastError);
    }

    public function provideDeprecatedSuper()
    {
        return [
            ['DeprecatedInterfaceClass', 'DeprecatedInterface', 'implements'],
            ['DeprecatedParentClass', 'DeprecatedClass', 'extends'],
        ];
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

        $xError = [
            'type' => E_USER_NOTICE,
            'message' => '',
        ];

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

        $xError = [
            'type' => E_USER_NOTICE,
            'message' => '',
        ];

        $this->assertSame($xError, $lastError);
    }

    public function testExtendedFinalClass()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        require __DIR__.'/Fixtures/FinalClasses.php';

        $i = 1;
        while (class_exists($finalClass = __NAMESPACE__.'\\Fixtures\\FinalClass'.$i++, false)) {
            spl_autoload_call($finalClass);
            class_exists('Test\\'.__NAMESPACE__.'\\Extends'.substr($finalClass, strrpos($finalClass, '\\') + 1), true);
        }

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass1" class is considered final since version 3.3. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass1".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass2" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass2".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass3" class is considered final comment with @@@ and ***. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass3".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass4" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass4".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass5" class is considered final multiline comment. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass5".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass6" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass6".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass7" class is considered final another multiline comment... It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass7".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalClass8" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsFinalClass8".',
        ], $deprecations);
    }

    public function testExtendedFinalMethod()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists(__NAMESPACE__.'\\Fixtures\\ExtendedFinalMethod', true);

        error_reporting($e);
        restore_error_handler();

        $xError = [
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalMethod::finalMethod()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\Debug\Tests\Fixtures\ExtendedFinalMethod".',
            'The "Symfony\Component\Debug\Tests\Fixtures\FinalMethod::finalMethod2()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\Debug\Tests\Fixtures\ExtendedFinalMethod".',
        ];

        $this->assertSame($xError, $deprecations);
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

        $this->assertSame(['type' => E_USER_NOTICE, 'message' => ''], $lastError);
    }

    public function testInternalsUse()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsInternals', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame($deprecations, [
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalInterface" interface is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalClass" class is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalTrait" trait is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\Debug\Tests\ExtendsInternals".',
            'The "Symfony\Component\Debug\Tests\Fixtures\InternalClass::internalMethod()" method is considered internal. It may change without further notice. You should not extend it from "Test\Symfony\Component\Debug\Tests\ExtendsInternals".',
        ]);
    }

    public function testExtendedMethodDefinesNewParameters()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists(__NAMESPACE__.'\\Fixtures\SubClassWithAnnotatedParameters', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::quzMethod()" method will require a new "Quz $quz" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\ClassWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::whereAmI()" method will require a new "bool $matrix" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "$noType" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "callable(\Throwable|null $reason, mixed $value) $callback" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "string $param" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "callable  ($a,  $b) $anotherOne" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "Type$WithDollarIsStillAType $ccc" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\Debug\Tests\Fixtures\SubClassWithAnnotatedParameters::isSymfony()" method will require a new "true $yes" argument in the next major version of its parent class "Symfony\Component\Debug\Tests\Fixtures\ClassWithAnnotatedParameters", not defining it is deprecated.',
        ], $deprecations);
    }

    public function testUseTraitWithInternalMethod()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\UseTraitWithInternalMethod', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testVirtualUse()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsVirtual', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethodNoBraces()".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::newLineInterfaceMethod()": Some description!',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::newLineInterfaceMethodNoBraces()": Description.',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::invalidInterfaceMethod()".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::invalidInterfaceMethodNoBraces()".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::complexInterfaceMethod($arg, ...$args)".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::complexInterfaceMethodTyped($arg, int ...$args)": Description ...',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::staticMethodNoBraces()".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::staticMethodTyped(int $arg)": Description.',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\Debug\Tests\Fixtures\VirtualInterface::staticMethodTypedNoBraces()".',
            'Class "Test\Symfony\Component\Debug\Tests\ExtendsVirtual" should implement method "Symfony\Component\Debug\Tests\Fixtures\VirtualSubInterface::subInterfaceMethod()".',
        ], $deprecations);
    }

    public function testVirtualUseWithMagicCall()
    {
        $deprecations = [];
        set_error_handler(function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\ExtendsVirtualMagicCall', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testEvaluatedCode()
    {
        $this->assertTrue(class_exists(__NAMESPACE__.'\Fixtures\DefinitionInEvaluatedCode', true));
    }
}

class ClassLoader
{
    public function loadClass($class)
    {
    }

    public function getClassMap()
    {
        return [__NAMESPACE__.'\Fixtures\NotPSR0bis' => __DIR__.'/Fixtures/notPsr0Bis.php'];
    }

    public function findFile($class)
    {
        $fixtureDir = __DIR__.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR;

        if (__NAMESPACE__.'\TestingUnsilencing' === $class) {
            eval('-- parse error --');
        } elseif (__NAMESPACE__.'\TestingStacking' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingStacking { function foo() {} }');
        } elseif (__NAMESPACE__.'\TestingCaseMismatch' === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingCaseMisMatch {}');
        } elseif (__NAMESPACE__.'\Fixtures\Psr4CaseMismatch' === $class) {
            return $fixtureDir.'psr4'.\DIRECTORY_SEPARATOR.'Psr4CaseMismatch.php';
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
        } elseif (0 === strpos($class, 'Test\\'.__NAMESPACE__.'\ExtendsFinalClass')) {
            $classShortName = substr($class, strrpos($class, '\\') + 1);
            eval('namespace Test\\'.__NAMESPACE__.'; class '.$classShortName.' extends \\'.__NAMESPACE__.'\Fixtures\\'.substr($classShortName, 7).' {}');
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
        } elseif ('Test\\'.__NAMESPACE__.'\UseTraitWithInternalMethod' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class UseTraitWithInternalMethod { use \\'.__NAMESPACE__.'\Fixtures\TraitWithInternalMethod; }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsVirtual' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtual extends ExtendsVirtualParent implements \\'.__NAMESPACE__.'\Fixtures\VirtualSubInterface {
                public function ownClassMethod() { }
                public function classMethod() { }
                public function sameLineInterfaceMethodNoBraces() { }
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsVirtualParent' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualParent extends ExtendsVirtualAbstract {
                public function ownParentMethod() { }
                public function traitMethod() { }
                public function sameLineInterfaceMethod() { }
                public function staticMethodNoBraces() { } // should be static
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsVirtualAbstract' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; abstract class ExtendsVirtualAbstract extends ExtendsVirtualAbstractBase {
                public static function staticMethod() { }
                public function ownAbstractMethod() { }
                public function interfaceMethod() { }
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsVirtualAbstractBase' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; abstract class ExtendsVirtualAbstractBase extends \\'.__NAMESPACE__.'\Fixtures\VirtualClass implements \\'.__NAMESPACE__.'\Fixtures\VirtualInterface {
                public function ownAbstractBaseMethod() { }
            }');
        } elseif ('Test\\'.__NAMESPACE__.'\ExtendsVirtualMagicCall' === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualMagicCall extends \\'.__NAMESPACE__.'\Fixtures\VirtualClassMagicCall implements \\'.__NAMESPACE__.'\Fixtures\VirtualInterface {
            }');
        }

        return null;
    }
}
