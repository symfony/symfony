<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\ErrorHandler\Tests\Fixtures\ExtendsDeprecatedParent;
use Symfony\Component\DependencyInjection\Tests\Fixtures\DeprecatedClass;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\ErrorHandler\Tests\Fixtures\ExtendsDeprecatedClassInTheSameVendor;

class DebugClassLoaderTest extends TestCase
{
    private string|false $patchTypes;
    private int $errorReporting;
    private array $loader;

    protected function setUp(): void
    {
        $this->patchTypes = getenv('SYMFONY_PATCH_TYPE_DECLARATIONS');
        $this->errorReporting = error_reporting(\E_ALL);
        putenv('SYMFONY_PATCH_TYPE_DECLARATIONS=deprecations=1');
        $this->loader = [new DebugClassLoader([new ClassLoader(), 'loadClass']), 'loadClass'];
        spl_autoload_register($this->loader, true, true);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->loader);
        error_reporting($this->errorReporting);
        putenv('SYMFONY_PATCH_TYPE_DECLARATIONS'.(false !== $this->patchTypes ? '='.$this->patchTypes : ''));
    }

    #[RunInSeparateProcess]
    public function testIdempotence()
    {
        DebugClassLoader::enable();
        DebugClassLoader::enable();

        $functions = spl_autoload_functions();
        foreach ($functions as $function) {
            if (\is_array($function) && $function[0] instanceof DebugClassLoader) {
                $reflClass = new \ReflectionClass($function[0]);
                $reflProp = $reflClass->getProperty('classLoader');

                $this->assertNotInstanceOf(DebugClassLoader::class, $reflProp->getValue($function[0]));

                return;
            }
        }

        $this->fail('DebugClassLoader did not register');
    }

    #[RunInSeparateProcess]
    public function testEnableClearsVendorPrefixCacheOnRemapChange()
    {
        $reflClass = new \ReflectionClass(DebugClassLoader::class);
        $cacheProp = $reflClass->getProperty('vendorPrefixCache');
        $remapProp = $reflClass->getProperty('namespaceRemappings');

        DebugClassLoader::enable(['App' => 'Symfony']);
        $cacheProp->setValue(null, ['App\\Foo' => 'Symfony']);

        DebugClassLoader::enable(['App' => 'Acme']);

        $this->assertSame([], $cacheProp->getValue(), 'enable() must clear the vendor prefix cache');
        $this->assertSame(['App' => 'Acme'], $remapProp->getValue(), 'enable() must replace the namespace remappings');
    }

    public function testThrowingClass()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('boo');
        try {
            class_exists(Fixtures\Throwing::class);
            $this->fail('Exception expected');
        } catch (\Exception $e) {
            $this->assertSame('boo', $e->getMessage());
        }

        // the second call also should throw
        class_exists(Fixtures\Throwing::class);
    }

    public function testNameCaseMismatch()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Case mismatch between loaded and declared class names');
        class_exists(TestingCaseMismatch::class, true);
    }

    public function testFileCaseMismatch()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Case mismatch between class and real file names');
        if (!file_exists(__DIR__.'/Fixtures/CaseMismatch.php')) {
            $this->markTestSkipped('Can only be run on case-insensitive filesystems');
        }

        class_exists(Fixtures\CaseMismatch::class, true);
    }

    public function testPsr4CaseMismatch()
    {
        $this->expectException(\RuntimeException::class);
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
        $this->assertTrue(class_exists(Fixtures\ClassAlias::class, true));
    }

    #[DataProvider('provideDeprecatedSuper')]
    public function testDeprecatedSuper(string $class, string $super, string $type)
    {
        set_error_handler(static fn () => false);
        $e = error_reporting(0);
        trigger_error('', \E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\\'.$class, true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = [
            'type' => \E_USER_DEPRECATED,
            'message' => 'The "Test\Symfony\Component\ErrorHandler\Tests\\'.$class.'" class '.$type.' "Symfony\Component\ErrorHandler\Tests\Fixtures\\'.$super.'" that is deprecated but this is a test deprecation notice.',
        ];

        $this->assertSame($xError, $lastError);
    }

    public static function provideDeprecatedSuper(): array
    {
        return [
            ['DeprecatedInterfaceClass', 'DeprecatedInterface', 'implements'],
            ['DeprecatedParentClass', 'DeprecatedClass', 'extends'],
        ];
    }

    public function testInterfaceExtendsDeprecatedInterface()
    {
        set_error_handler(static fn () => false);
        $e = error_reporting(0);
        trigger_error('', \E_USER_NOTICE);

        class_exists('Test\\'.NonDeprecatedInterfaceClass::class, true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = [
            'type' => \E_USER_NOTICE,
            'message' => '',
        ];

        $this->assertSame($xError, $lastError);
    }

    public function testDeprecatedSuperInSameNamespace()
    {
        set_error_handler(static fn () => false);
        $e = error_reporting(0);
        trigger_error('', \E_USER_NOTICE);

        class_exists(ExtendsDeprecatedParent::class, true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $xError = [
            'type' => \E_USER_NOTICE,
            'message' => '',
        ];

        $this->assertSame($xError, $lastError);
    }

    public function testExtendedFinalClass()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        require __DIR__.'/Fixtures/FinalClasses.php';

        $i = 1;
        while (class_exists($finalClass = Fixtures\FinalClass::class.$i++, false)) {
            spl_autoload_call($finalClass);
            class_exists('Test\\'.__NAMESPACE__.'\\Extends'.substr($finalClass, strrpos($finalClass, '\\') + 1), true);
        }

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass1" class is considered final since version 3.3. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass1".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass2" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass2".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass3" class is considered final comment with @@@ and ***. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass3".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass4" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass4".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass5" class is considered final multiline comment. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass5".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass6" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass6".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass7" class is considered final another multiline comment... It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass7".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalClass8" class is considered final. It may change without further notice as of its next major version. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsFinalClass8".',
        ], $deprecations);
    }

    public function testExtendedFinalMethod()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists(Fixtures\ExtendedFinalMethod::class, true);

        error_reporting($e);
        restore_error_handler();

        $xError = [
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalMethod::finalMethod()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\ErrorHandler\Tests\Fixtures\ExtendedFinalMethod".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalMethod::finalMethod2()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "Symfony\Component\ErrorHandler\Tests\Fixtures\ExtendedFinalMethod".',
        ];

        $this->assertSame($xError, $deprecations);
    }

    public function testExtendedDeprecatedMethodDoesntTriggerAnyNotice()
    {
        set_error_handler(static fn () => false);
        $e = error_reporting(0);
        trigger_error('', \E_USER_NOTICE);

        class_exists('Test\\'.ExtendsAnnotatedClass::class, true);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $this->assertSame(['type' => \E_USER_NOTICE, 'message' => ''], $lastError);
    }

    public function testInternalsUse()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsInternals::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\InternalInterface" interface is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\InternalClass" class is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsInternalsParent".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\InternalTrait" trait is considered internal. It may change without further notice. You should not use it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsInternals".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\InternalClass::internalMethod()" method is considered internal. It may change without further notice. You should not extend it from "Test\Symfony\Component\ErrorHandler\Tests\ExtendsInternals".',
        ], $deprecations);
    }

    public function testExtendedMethodDefinesNewParameters()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists(Fixtures\SubClassWithAnnotatedParameters::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::quzMethod()" method will require a new "Quz $quz" argument in the next major version of its parent class "Symfony\Component\ErrorHandler\Tests\Fixtures\ClassWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::whereAmI()" method will require a new "bool $matrix" argument in the next major version of its interface "Symfony\Component\ErrorHandler\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "$noType" argument in the next major version of its interface "Symfony\Component\ErrorHandler\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "callable $callback" argument in the next major version of its interface "Symfony\Component\ErrorHandler\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "string $param" argument in the next major version of its interface "Symfony\Component\ErrorHandler\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::iAmHere()" method will require a new "callable $anotherOne" argument in the next major version of its interface "Symfony\Component\ErrorHandler\Tests\Fixtures\InterfaceWithAnnotatedParameters", not defining it is deprecated.',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\SubClassWithAnnotatedParameters::isSymfony()" method will require a new "true $yes" argument in the next major version of its parent class "Symfony\Component\ErrorHandler\Tests\Fixtures\ClassWithAnnotatedParameters", not defining it is deprecated.',
        ], $deprecations);
    }

    public function testUseTraitWithInternalMethod()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.UseTraitWithInternalMethod::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testVirtualUse()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtual::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethod()" might add "Foo&Bar" as a native return type declaration in the future. Do the same in implementation "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualAbstract" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::interfaceMethod()" might add "string" as a native return type declaration in the future. Do the same in implementation "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualAbstract" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticReturningMethod(): static".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethodNoBraces()".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethod()": Some description!',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethodNoBraces(): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethod(): unknownType".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethodNoBraces(): unknownType|string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethod($arg, ...$args)".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethodTyped($arg, int ...$args): array<string, int>|string[]|int": Description ...',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodNoBraces(): mixed".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTyped(int $arg): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTypedNoBraces(): \stdClass[]".',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodNoBraces()" might add "mixed" as a native return type declaration in the future. Do the same in implementation "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualParent" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtual" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualSubInterface::subInterfaceMethod(): string".',
        ], $deprecations);
    }

    public function testVirtualUseWithInheritedInterface()
    {
        // A concrete class implementing a child interface should also receive notices for @method
        // annotations declared on parent interfaces, even without abstract classes in between.
        // (ExtendsVirtualSubInterfaceDirect implements VirtualSubInterface, which extends VirtualInterface)

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtualSubInterfaceDirect::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualSubInterface::subInterfaceMethod(): string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::interfaceMethod(): string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticReturningMethod(): static".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethod($arg)".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethodNoBraces()".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethod()": Some description!',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethodNoBraces(): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethod(): unknownType".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethodNoBraces(): unknownType|string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethod($arg, ...$args)".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethodTyped($arg, int ...$args): array<string, int>|string[]|int": Description ...',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethod(): Foo&Bar".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodNoBraces(): mixed".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTyped(int $arg): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualSubInterfaceDirect" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTypedNoBraces(): \stdClass[]".',
        ], $deprecations);
    }

    public function testVirtualUseWithMagicCall()
    {
        // This is like the preceding testVirtualUse() test, but this time the class contains
        // __call/__callStatic magic methods. We want the notices to be triggered in this case:
        // If the interface changes the "@method" to a real declaration in the future, the class
        // will need to contain that method.

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtualMagicCall::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::interfaceMethod(): string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticReturningMethod(): static".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethod($arg)".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::sameLineInterfaceMethodNoBraces()".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethod()": Some description!',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::newLineInterfaceMethodNoBraces(): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethod(): unknownType".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::invalidInterfaceMethodNoBraces(): unknownType|string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethod($arg, ...$args)".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::complexInterfaceMethodTyped($arg, int ...$args): array<string, int>|string[]|int": Description ...',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethod(): Foo&Bar".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodNoBraces(): mixed".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTyped(int $arg): \stdClass": Description.',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualMagicCall" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualInterface::staticMethodTypedNoBraces(): \stdClass[]".',
        ], $deprecations);
    }

    public function testVirtualUseWithAbstractClass()
    {
        // An abstract class can announce @method annotations the same way an interface does, to give
        // subclasses time to implement the method before it becomes a real abstract requirement.
        // ExtendsVirtualAbstractClass extends VirtualAbstract (abstract) and does not implement any of its
        // @method annotations.

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtualAbstractClass::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualAbstractClass" should implement method "Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualAbstract::abstractClassMethod(): string".',
            'Class "Test\Symfony\Component\ErrorHandler\Tests\ExtendsVirtualAbstractClass" should implement method "static Symfony\Component\ErrorHandler\Tests\Fixtures\VirtualAbstract::abstractStaticMethod(): \stdClass": Description.',
        ], $deprecations);
    }

    public function testVirtualUseWithAbstractClassImplementingTheMethod()
    {
        // When the concrete subclass already declares the announced @method, no deprecation is raised.

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtualAbstractClassImpl::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testVirtualUseWithMagicCallInterface()
    {
        // When an interface uses "@method" annotations and, at the same time, requires the __call method to be
        // implemented, do not trigger notices. The assumption is that this interface documents an API contract
        // using a pattern of magic calls, for example like https://github.com/predis/predis/blob/deee2b6d605eb6401446f6f6354414ab7571a5a0/src/ClientInterface.php.
        // This has the risk of false negatives, i. e. missing notices in case the interface intends to add real
        // methods in the future.

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ExtendsVirtualMagicCallInterface::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testEvaluatedCode()
    {
        $this->assertTrue(class_exists(Fixtures\DefinitionInEvaluatedCode::class, true));
    }

    #[RunInSeparateProcess]
    #[DataProvider('provideExposeDeprecations')]
    public function testExposeDeprecations(bool $expectDeprecation, ?array $deprecationsNamespacesMapping)
    {
        DebugClassLoader::enable($deprecationsNamespacesMapping);

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        new ExtendsDeprecatedClassInTheSameVendor();

        error_reporting($e);
        restore_error_handler();

        $this->assertSame($expectDeprecation ? [
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\ExtendsDeprecatedClassInTheSameVendor" class extends "Symfony\Component\ErrorHandler\Tests\Fixtures\DeprecatedClass" that is deprecated but this is a test deprecation notice.',
        ] : [], $deprecations);
    }

    public static function provideExposeDeprecations(): array
    {
        return [
            [false, null], // default current behavior -> should not be exposed
            [false, []], // no matching (empty array) -> should not be exposed
            [false, ['No\Matching' => 'foo']], // no matching -> should not be exposed
            [true, [ExtendsDeprecatedClassInTheSameVendor::class => 'foo']], // only $class matched -> different vendors -> should be exposed
            [false, ['Symfony\Component\ErrorHandler\Tests\Fixtures' => 'foo']], // both $class and $use matched to same vendor -> should not be exposed
            [false, ['Symfony\Component\ErrorHandler' => 'foo']], // both $class and $use matched to same vendor -> should not be exposed
            [false, ['Symfony' => 'foo']], // both $class and $use matched to same vendor -> should not be exposed
            [true, [ExtendsDeprecatedClassInTheSameVendor::class => 'foo', DeprecatedClass::class => 'bar']], // both matched but to different vendors -> should be exposed
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('provideMuteDeprecations')]
    public function testMuteDeprecations(bool $expectDeprecation, ?array $deprecationsNamespacesMapping)
    {
        DebugClassLoader::enable($deprecationsNamespacesMapping);

        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.__NAMESPACE__.'\DeprecatedParentClass', true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame($expectDeprecation ? [
            'The "Test\Symfony\Component\ErrorHandler\Tests\DeprecatedParentClass" class extends "Symfony\Component\ErrorHandler\Tests\Fixtures\DeprecatedClass" that is deprecated but this is a test deprecation notice.',
        ] : [], $deprecations);
    }

    public static function provideMuteDeprecations(): array
    {
        return [
            [true, null], // default current behavior -> should not be muted
            [true, []], // no matching (empty array) -> should not be muted
            [true, ['No\Matching' => 'foo']], // no matching -> should not be muted
            [true, ['Test' => 'No\Matching']], // only $class matched, vendors differ -> should not be muted
            [false, ['Test\\'.__NAMESPACE__.'\DeprecatedParentClass' => 'x', 'Symfony\Component\ErrorHandler\Tests\Fixtures\DeprecatedClass' => 'x']], // both matched to same vendor via FQCN -> should be muted
            [false, ['Test\\'.__NAMESPACE__ => 'x', 'Symfony\Component\ErrorHandler\Tests\Fixtures' => 'x']], // both matched to same vendor via namespace prefix -> should be muted
            [false, ['Test' => 'Symfony']], // $class matched to 'Symfony', $use default first segment 'Symfony' -> same vendor -> should be muted
            [true, ['Test' => 'one', 'Symfony' => 'two']], // both matched but to different vendors -> should not be muted
        ];
    }

    public function testRootNamespaceDontTriggerDeprecations()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        require __DIR__.'/Fixtures/RootNamespace.php';

        spl_autoload_call(\RootNamespace::class);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([], $deprecations);
    }

    public function testReturnType()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ReturnType::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeGrandParent::returnTypeGrandParent()" might add "string" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParentInterface::returnTypeParentInterface()" might add "string" as a native return type declaration in the future. Do the same in implementation "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeInterface::returnTypeInterface()" might add "string" as a native return type declaration in the future. Do the same in implementation "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneNonNullableReturnableType()" might add "void" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneNonNullableReturnableTypeWithNull()" might add "void" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneNullableReturnableType()" might add "array" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneNullableReturnableTypeWithNull()" might add "?bool" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneOtherType()" might add "\ArrayIterator" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::oneOtherTypeWithNull()" might add "?\ArrayIterator" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::twoNullableReturnableTypes()" might add "int|\Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::threeReturnTypes()" might add "bool|string|null" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::manyIterables()" might add "array" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::nullableReturnableTypeNormalization()" might add "object" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::nonNullableReturnableTypeNormalization()" might add "void" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::bracketsNormalization()" might add "array" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::booleanNormalization()" might add "false" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::callableNormalization1()" might add "callable" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::callableNormalization2()" might add "callable" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::otherTypeNormalization()" might add "\ArrayIterator" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::arrayWithLessThanSignNormalization()" might add "array" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::this()" might add "static" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::mixed()" might add "mixed" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::nullableMixed()" might add "mixed" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::static()" might add "static" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::false()" might add "false" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::true()" might add "true" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::never()" might add "never" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::null()" might add "null" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParent::classConstant()" might add "string" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnType" now to avoid errors or add an explicit @return annotation to suppress this message.',
        ], $deprecations);
    }

    public function testReturnTypePhp83()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists('Test\\'.ReturnTypePhp83::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'Method "Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeParentPhp83::classConstantWithType()" might add "string" as a native return type declaration in the future. Do the same in child class "Test\Symfony\Component\ErrorHandler\Tests\ReturnTypePhp83" now to avoid errors or add an explicit @return annotation to suppress this message.',
        ], $deprecations);
    }

    public function testOverrideFinalProperty()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists(Fixtures\OverrideFinalProperty::class, true);
        class_exists(Fixtures\FinalProperty\OverrideFinalPropertySameNamespace::class, true);
        class_exists('Test\\'.OverrideOutsideFinalProperty::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty\FinalProperty::$pub" property is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\OverrideFinalProperty".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty\FinalProperty::$prot" property is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\OverrideFinalProperty".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalProperty\FinalProperty::$implicitlyFinal" property is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\OverrideFinalProperty".',
            'The "Test\Symfony\Component\ErrorHandler\Tests\FinalProperty\OutsideFinalProperty::$final" property is considered final. You should not override it in "Test\Symfony\Component\ErrorHandler\Tests\OverrideOutsideFinalProperty".',
        ], $deprecations);
    }

    public function testOverrideFinalConstant()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists(Fixtures\FinalConstant\OverrideFinalConstant::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\FinalConstants::OVERRIDDEN_FINAL_PARENT_CLASS" constant is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\OverrideFinalConstant".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\FinalConstants2::OVERRIDDEN_FINAL_PARENT_PARENT_CLASS" constant is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\OverrideFinalConstant".',
        ], $deprecations);
    }

    public function testOverrideFinalConstant81()
    {
        $deprecations = [];
        set_error_handler(static function ($type, $msg) use (&$deprecations) { $deprecations[] = $msg; });
        $e = error_reporting(\E_USER_DEPRECATED);

        class_exists(Fixtures\FinalConstant\OverrideFinalConstant81::class, true);

        error_reporting($e);
        restore_error_handler();

        $this->assertSame([
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\FinalConstantsInterface::OVERRIDDEN_FINAL_INTERFACE" constant is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\OverrideFinalConstant81".',
            'The "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\FinalConstantsInterface2::OVERRIDDEN_FINAL_INTERFACE_2" constant is considered final. You should not override it in "Symfony\Component\ErrorHandler\Tests\Fixtures\FinalConstant\OverrideFinalConstant81".',
        ], $deprecations);
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

        if (TestingUnsilencing::class === $class) {
            eval('-- parse error --');
        } elseif (TestingStacking::class === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingStacking { function foo() {} }');
        } elseif (TestingCaseMismatch::class === $class) {
            eval('namespace '.__NAMESPACE__.'; class TestingCaseMisMatch {}');
        } elseif (__NAMESPACE__.'\Fixtures\Psr4CaseMismatch' === $class) {
            return $fixtureDir.'psr4'.\DIRECTORY_SEPARATOR.'Psr4CaseMismatch.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0' === $class) {
            return $fixtureDir.'reallyNotPsr0.php';
        } elseif (__NAMESPACE__.'\Fixtures\NotPSR0bis' === $class) {
            return $fixtureDir.'notPsr0Bis.php';
        } elseif ('Symfony\Bridge\Debug\Tests\Fixtures\ExtendsDeprecatedParent' === $class) {
            eval('namespace Symfony\Bridge\Debug\Tests\Fixtures; class ExtendsDeprecatedParent extends \\'.__NAMESPACE__.'\Fixtures\DeprecatedClass {}');
        } elseif ('Test\\'.DeprecatedParentClass::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class DeprecatedParentClass extends \\'.__NAMESPACE__.'\Fixtures\DeprecatedClass {}');
        } elseif ('Test\\'.DeprecatedInterfaceClass::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class DeprecatedInterfaceClass implements \\'.__NAMESPACE__.'\Fixtures\DeprecatedInterface {}');
        } elseif ('Test\\'.NonDeprecatedInterfaceClass::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class NonDeprecatedInterfaceClass implements \\'.__NAMESPACE__.'\Fixtures\NonDeprecatedInterface {}');
        } elseif ('Test\\'.Float::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class Float {}');
        } elseif (str_starts_with($class, 'Test\\'.ExtendsFinalClass::class)) {
            $classShortName = substr($class, strrpos($class, '\\') + 1);
            eval('namespace Test\\'.__NAMESPACE__.'; class '.$classShortName.' extends \\'.__NAMESPACE__.'\Fixtures\\'.substr($classShortName, 7).' {}');
        } elseif ('Test\\'.ExtendsAnnotatedClass::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsAnnotatedClass extends \\'.__NAMESPACE__.'\Fixtures\AnnotatedClass {
                public function deprecatedMethod() { }
            }');
        } elseif ('Test\\'.ExtendsInternals::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsInternals extends ExtendsInternalsParent {
                use \\'.__NAMESPACE__.'\Fixtures\InternalTrait;

                public function internalMethod() { }
            }');
        } elseif ('Test\\'.ExtendsInternalsParent::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsInternalsParent extends \\'.__NAMESPACE__.'\Fixtures\InternalClass implements \\'.__NAMESPACE__.'\Fixtures\InternalInterface { }');
        } elseif ('Test\\'.UseTraitWithInternalMethod::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class UseTraitWithInternalMethod { use \\'.__NAMESPACE__.'\Fixtures\TraitWithInternalMethod; }');
        } elseif ('Test\\'.ExtendsVirtual::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtual extends ExtendsVirtualParent implements \\'.__NAMESPACE__.'\Fixtures\VirtualSubInterface {
                public function ownClassMethod() { }
                public function classMethod() { }
                public function sameLineInterfaceMethodNoBraces() { }
            }');
        } elseif ('Test\\'.ExtendsVirtualParent::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualParent extends ExtendsVirtualAbstract {
                public function ownParentMethod() { }
                public function traitMethod() { }
                public function sameLineInterfaceMethod() { }
                public function staticMethodNoBraces() { } // should be static
            }');
        } elseif ('Test\\'.ExtendsVirtualAbstract::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; abstract class ExtendsVirtualAbstract extends ExtendsVirtualAbstractBase {
                public static function staticMethod() { }
                public function ownAbstractMethod() { }
                public function interfaceMethod() { }
            }');
        } elseif ('Test\\'.ExtendsVirtualAbstractBase::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; abstract class ExtendsVirtualAbstractBase extends \\'.__NAMESPACE__.'\Fixtures\VirtualClass implements \\'.__NAMESPACE__.'\Fixtures\VirtualInterface {
                public function ownAbstractBaseMethod() { }
            }');
        } elseif ('Test\\'.ExtendsVirtualSubInterfaceDirect::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualSubInterfaceDirect implements \\'.__NAMESPACE__.'\Fixtures\VirtualSubInterface {
            }');
        } elseif ('Test\\'.ExtendsVirtualMagicCall::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualMagicCall extends \\'.__NAMESPACE__.'\Fixtures\VirtualClassMagicCall implements \\'.__NAMESPACE__.'\Fixtures\VirtualInterface {
            }');
        } elseif ('Test\\'.ExtendsVirtualMagicCallInterface::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualMagicCallInterface implements \\'.__NAMESPACE__.'\Fixtures\VirtualInterfaceWithCall {
                public function __call(string $name, array $arguments): mixed { return null; }
            }');
        } elseif ('Test\\'.ExtendsVirtualAbstractClass::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualAbstractClass extends \\'.__NAMESPACE__.'\Fixtures\VirtualAbstract {
            }');
        } elseif ('Test\\'.ExtendsVirtualAbstractClassImpl::class === $class) {
            eval('namespace Test\\'.__NAMESPACE__.'; class ExtendsVirtualAbstractClassImpl extends \\'.__NAMESPACE__.'\Fixtures\VirtualAbstract {
                public function abstractClassMethod(): string { return ""; }
                public static function abstractStaticMethod(): \stdClass { return new \stdClass(); }
            }');
        } elseif ('Test\\'.ReturnType::class === $class) {
            return $fixtureDir.\DIRECTORY_SEPARATOR.'ReturnType.php';
        } elseif ('Test\\'.ReturnTypePhp83::class === $class) {
            return $fixtureDir.\DIRECTORY_SEPARATOR.'ReturnTypePhp83.php';
        } elseif ('Test\\'.Fixtures\OutsideInterface::class === $class) {
            return $fixtureDir.\DIRECTORY_SEPARATOR.'OutsideInterface.php';
        } elseif ('Test\\'.OverrideOutsideFinalProperty::class === $class) {
            return $fixtureDir.'OverrideOutsideFinalProperty.php';
        } elseif ('Test\\Symfony\\Component\\ErrorHandler\\Tests\\FinalProperty\\OutsideFinalProperty' === $class) {
            return $fixtureDir.'FinalProperty'.\DIRECTORY_SEPARATOR.'OutsideFinalProperty.php';
        }
    }
}

// @php-cs-fixer-ignore error_suppression This file is explicitly expected to not silence each of trigger_error calls
