<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV5;
use Symfony\Bridge\PhpUnit\SetUpTearDownTrait;

class DeprecationTest extends TestCase
{
    use SetUpTearDownTrait;

    private static $vendorDir;

    private static function getVendorDir()
    {
        if (null !== self::$vendorDir) {
            return self::$vendorDir;
        }

        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $vendorDir = \dirname(\dirname($r->getFileName()));
                if (file_exists($vendorDir.'/composer/installed.json') && @mkdir($vendorDir.'/myfakevendor/myfakepackage1', 0777, true)) {
                    break;
                }
            }
        }

        self::$vendorDir = $vendorDir;
        mkdir($vendorDir.'/myfakevendor/myfakepackage2');
        touch($vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile1.php');
        touch($vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile2.php');
        touch($vendorDir.'/myfakevendor/myfakepackage2/MyFakeFile.php');

        return self::$vendorDir;
    }

    public function testItCanDetermineTheClassWhereTheDeprecationHappened()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->originatesFromAnObject());
        $this->assertSame(self::class, $deprecation->originatingClass());
        $this->assertSame(__FUNCTION__, $deprecation->originatingMethod());
    }

    public function testItCanTellWhetherItIsInternal()
    {
        $r = new \ReflectionClass(Deprecation::class);

        if (\dirname(\dirname($r->getFileName())) !== \dirname(\dirname(__DIR__))) {
            $this->markTestSkipped('Test case is not compatible with having the bridge in vendor/');
        }

        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertSame(Deprecation::TYPE_SELF, $deprecation->getType());
    }

    public function testLegacyTestMethodIsDetectedAsSuch()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->isLegacy('whatever'));
    }

    public function testItCanBeConvertedToAString()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertStringContainsString('💩', $deprecation->toString());
        $this->assertStringContainsString(__FUNCTION__, $deprecation->toString());
    }

    public function testItRulesOutFilesOutsideVendorsAsIndirect()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertNotSame(Deprecation::TYPE_INDIRECT, $deprecation->getType());
    }

    /**
     * @dataProvider mutedProvider
     */
    public function testItMutesOnlySpecificErrorMessagesWhenTheCallingCodeIsInPhpunit($muted, $callingClass, $message)
    {
        $trace = $this->debugBacktrace();
        array_unshift($trace, ['class' => $callingClass]);
        array_unshift($trace, ['class' => DeprecationErrorHandler::class]);
        $deprecation = new Deprecation($message, $trace, 'should_not_matter.php');
        $this->assertSame($muted, $deprecation->isMuted());
    }

    public function mutedProvider()
    {
        yield 'not from phpunit, and not a whitelisted message' => [
            false,
            \My\Source\Code::class,
            'Self deprecating humor is deprecated by itself',
        ];
        yield 'from phpunit, but not a whitelisted message' => [
            false,
            \PHPUnit\Random\Piece\Of\Code::class,
            'Self deprecating humor is deprecated by itself',
        ];
        yield 'whitelisted message, but not from phpunit' => [
            false,
            \My\Source\Code::class,
            'Function ReflectionType::__toString() is deprecated',
        ];
        yield 'from phpunit and whitelisted message' => [
            true,
            \PHPUnit\Random\Piece\Of\Code::class,
            'Function ReflectionType::__toString() is deprecated',
        ];
    }

    public function testNotMutedIfNotCalledFromAClassButARandomFile()
    {
        $deprecation = new Deprecation(
            'Function ReflectionType::__toString() is deprecated',
            [
                ['file' => 'should_not_matter.php'],
                ['file' => 'should_not_matter_either.php'],
            ],
            'my-procedural-controller.php'
        );
        $this->assertFalse($deprecation->isMuted());
    }

    public function testItTakesMutesDeprecationFromPhpUnitFiles()
    {
        $deprecation = new Deprecation(
            'Function ReflectionType::__toString() is deprecated',
            [
                ['file' => 'should_not_matter.php'],
                ['file' => 'should_not_matter_either.php'],
            ],
            'random_path'.\DIRECTORY_SEPARATOR.'vendor'.\DIRECTORY_SEPARATOR.'phpunit'.\DIRECTORY_SEPARATOR.'whatever.php'
        );
        $this->assertTrue($deprecation->isMuted());
    }

    public function providerGetTypeDetectsSelf()
    {
        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && 0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $v = \dirname(\dirname($r->getFileName()));
                if (file_exists($v.'/composer/installed.json')) {
                    $loader = require $v.'/autoload.php';
                    $reflection = new \ReflectionClass($loader);
                    $prop = $reflection->getProperty('prefixDirsPsr4');
                    $prop->setAccessible(true);
                    $currentValue = $prop->getValue($loader);
                    $currentValue['Symfony\\Bridge\\PhpUnit\\'] = [realpath(__DIR__.'/../..')];
                    $prop->setValue($loader, $currentValue);
                }
            }
        }

        return [
            'not_from_vendors_file' => [Deprecation::TYPE_SELF, '', 'MyClass1', __FILE__],
            'nonexistent_file' => [Deprecation::TYPE_UNDETERMINED, '', 'MyClass1', 'dummy_vendor_path'],
            'serialized_trace_with_nonexistent_triggering_file' => [
                Deprecation::TYPE_UNDETERMINED,
                serialize([
                    'class' => '',
                    'method' => '',
                    'deprecation' => '',
                    'triggering_file' => 'dummy_vendor_path',
                    'files_stack' => [],
                ]),
                SymfonyTestsListenerForV5::class,
                '',
            ],
        ];
    }

    /**
     * @dataProvider providerGetTypeDetectsSelf
     */
    public function testGetTypeDetectsSelf(string $expectedType, string $message, string $traceClass, string $file)
    {
        $trace = [
            ['class' => 'MyClass1', 'function' => 'myMethod'],
            ['class' => $traceClass, 'function' => 'myMethod'],
        ];
        $deprecation = new Deprecation($message, $trace, $file);
        $this->assertSame($expectedType, $deprecation->getType());
    }

    public function providerGetTypeUsesRightTrace()
    {
        $vendorDir = self::getVendorDir();

        return [
            'no_file_in_stack' => [Deprecation::TYPE_DIRECT, '', [['function' => 'myfunc1'], ['function' => 'myfunc2']]],
            'files_in_stack_from_various_packages' => [
                Deprecation::TYPE_INDIRECT,
                '',
                [
                    ['function' => 'myfunc1', 'file' => $vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile1.php'],
                    ['function' => 'myfunc2', 'file' => $vendorDir.'/myfakevendor/myfakepackage2/MyFakeFile.php'],
                ],
            ],
            'serialized_stack_files_from_same_package' => [
                Deprecation::TYPE_DIRECT,
                serialize([
                    'deprecation' => 'My deprecation message',
                    'class' => 'MyClass',
                    'method' => 'myMethod',
                    'files_stack' => [
                        $vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile1.php',
                        $vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile2.php',
                    ],
                ]),
                [['function' => 'myfunc1'], ['class' => SymfonyTestsListenerForV5::class, 'method' => 'mymethod']],
            ],
            'serialized_stack_files_from_various_packages' => [
                Deprecation::TYPE_INDIRECT,
                serialize([
                    'deprecation' => 'My deprecation message',
                    'class' => 'MyClass',
                    'method' => 'myMethod',
                    'files_stack' => [
                        $vendorDir.'/myfakevendor/myfakepackage1/MyFakeFile1.php',
                        $vendorDir.'/myfakevendor/myfakepackage2/MyFakeFile.php',
                    ],
                ]),
                [['function' => 'myfunc1'], ['class' => SymfonyTestsListenerForV5::class, 'method' => 'mymethod']],
            ],
        ];
    }

    /**
     * @dataProvider providerGetTypeUsesRightTrace
     */
    public function testGetTypeUsesRightTrace(string $expectedType, string $message, array $trace)
    {
        $deprecation = new Deprecation(
            $message,
            $trace,
            self::getVendorDir().'/myfakevendor/myfakepackage2/MyFakeFile.php'
        );
        $this->assertSame($expectedType, $deprecation->getType());
    }

    /**
     * This method is here to simulate the extra level from the piece of code
     * triggering an error to the error handler.
     */
    public function debugBacktrace()
    {
        return debug_backtrace();
    }

    private static function removeDir($dir)
    {
        $files = glob($dir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } else {
                self::removeDir($file);
            }
        }
        rmdir($dir);
    }

    private static function doTearDownAfterClass()
    {
        self::removeDir(self::getVendorDir().'/myfakevendor');
    }
}
