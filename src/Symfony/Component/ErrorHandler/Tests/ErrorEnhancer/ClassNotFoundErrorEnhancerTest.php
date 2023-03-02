<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorEnhancer;

use Composer\Autoload\ClassLoader as ComposerClassLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\DebugClassLoader;
use Symfony\Component\ErrorHandler\Error\ClassNotFoundError;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Symfony\Component\ErrorHandler\ErrorEnhancer\ClassNotFoundErrorEnhancer;

class ClassNotFoundErrorEnhancerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (spl_autoload_functions() as $function) {
            if (!\is_array($function)) {
                continue;
            }

            // get class loaders wrapped by DebugClassLoader
            if ($function[0] instanceof DebugClassLoader) {
                $function = $function[0]->getClassLoader();

                if (!\is_array($function)) {
                    continue;
                }
            }

            if ($function[0] instanceof ComposerClassLoader) {
                $function[0]->add('Symfony_Component_ErrorHandler_Tests_Fixtures', \dirname(__DIR__, 5));
                break;
            }
        }
    }

    /**
     * @dataProvider provideClassNotFoundData
     */
    public function testEnhance(string $originalMessage, string $enhancedMessage, $autoloader = null)
    {
        try {
            if ($autoloader) {
                // Unregister all autoloaders to ensure the custom provided
                // autoloader is the only one to be used during the test run.
                $autoloaders = spl_autoload_functions();
                array_map('spl_autoload_unregister', $autoloaders);
                spl_autoload_register($autoloader);
            }

            $expectedLine = __LINE__ + 1;
            $error = (new ClassNotFoundErrorEnhancer())->enhance(new \Error($originalMessage));
        } finally {
            if ($autoloader) {
                spl_autoload_unregister($autoloader);
                array_map('spl_autoload_register', $autoloaders);
            }
        }

        $this->assertInstanceOf(ClassNotFoundError::class, $error);
        $this->assertSame($enhancedMessage, $error->getMessage());
        $this->assertSame(realpath(__FILE__), $error->getFile());
        $this->assertSame($expectedLine, $error->getLine());
    }

    public static function provideClassNotFoundData()
    {
        $autoloader = new ComposerClassLoader();
        $autoloader->add('Symfony\Component\ErrorHandler\Error\\', realpath(__DIR__.'/../../Error'));
        $autoloader->add('Symfony_Component_ErrorHandler_Tests_Fixtures', realpath(__DIR__.'/../../Tests/Fixtures'));

        $debugClassLoader = new DebugClassLoader([$autoloader, 'loadClass']);

        return [
            [
                'Class "WhizBangFactory" not found',
                "Attempted to load class \"WhizBangFactory\" from the global namespace.\nDid you forget a \"use\" statement?",
            ],
            [
                'Class \'WhizBangFactory\' not found',
                "Attempted to load class \"WhizBangFactory\" from the global namespace.\nDid you forget a \"use\" statement?",
            ],
            [
                'Class "Foo\\Bar\\WhizBangFactory" not found',
                "Attempted to load class \"WhizBangFactory\" from namespace \"Foo\\Bar\".\nDid you forget a \"use\" statement for another namespace?",
            ],
            [
                'Class \'Foo\\Bar\\WhizBangFactory\' not found',
                "Attempted to load class \"WhizBangFactory\" from namespace \"Foo\\Bar\".\nDid you forget a \"use\" statement for another namespace?",
            ],
            [
                'Interface "Foo\\Bar\\WhizBangInterface" not found',
                "Attempted to load interface \"WhizBangInterface\" from namespace \"Foo\\Bar\".\nDid you forget a \"use\" statement for another namespace?",
            ],
            [
                'Trait "Foo\\Bar\\WhizBangTrait" not found',
                "Attempted to load trait \"WhizBangTrait\" from namespace \"Foo\\Bar\".\nDid you forget a \"use\" statement for another namespace?",
            ],
            [
                'Class \'UndefinedFunctionError\' not found',
                "Attempted to load class \"UndefinedFunctionError\" from the global namespace.\nDid you forget a \"use\" statement for \"Symfony\Component\ErrorHandler\Error\UndefinedFunctionError\"?",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                'Class \'PEARClass\' not found',
                "Attempted to load class \"PEARClass\" from the global namespace.\nDid you forget a \"use\" statement for \"Symfony_Component_ErrorHandler_Tests_Fixtures_PEARClass\"?",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                'Class \'Foo\\Bar\\UndefinedFunctionError\' not found',
                "Attempted to load class \"UndefinedFunctionError\" from namespace \"Foo\Bar\".\nDid you forget a \"use\" statement for \"Symfony\Component\ErrorHandler\Error\UndefinedFunctionError\"?",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                'Class \'Foo\\Bar\\UndefinedFunctionError\' not found',
                "Attempted to load class \"UndefinedFunctionError\" from namespace \"Foo\Bar\".\nDid you forget a \"use\" statement for \"Symfony\Component\ErrorHandler\Error\UndefinedFunctionError\"?",
                [$autoloader, 'loadClass'],
            ],
            [
                'Class \'Foo\\Bar\\UndefinedFunctionError\' not found',
                "Attempted to load class \"UndefinedFunctionError\" from namespace \"Foo\Bar\".\nDid you forget a \"use\" statement for \"Symfony\Component\ErrorHandler\Error\UndefinedFunctionError\"?",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                'Class \'Foo\\Bar\\UndefinedFunctionError\' not found',
                "Attempted to load class \"UndefinedFunctionError\" from namespace \"Foo\\Bar\".\nDid you forget a \"use\" statement for another namespace?",
                function ($className) { /* do nothing here */ },
            ],
        ];
    }

    public function testEnhanceWithFatalError()
    {
        $error = (new ClassNotFoundErrorEnhancer())->enhance(new FatalError('foo', 0, [
            'type' => \E_ERROR,
            'message' => "Class 'FooBarCcc' not found",
            'file' => $expectedFile = realpath(__FILE__),
            'line' => $expectedLine = __LINE__,
        ]));

        $this->assertInstanceOf(ClassNotFoundError::class, $error);
        $this->assertSame("Attempted to load class \"FooBarCcc\" from the global namespace.\nDid you forget a \"use\" statement?", $error->getMessage());
        $this->assertSame($expectedFile, $error->getFile());
        $this->assertSame($expectedLine, $error->getLine());
    }

    public function testCannotRedeclareClass()
    {
        if (!file_exists(__DIR__.'/../FIXTURES2/REQUIREDTWICE.PHP')) {
            $this->markTestSkipped('Can only be run on case insensitive filesystems');
        }

        require_once __DIR__.'/../FIXTURES2/REQUIREDTWICE.PHP';

        $enhancer = new ClassNotFoundErrorEnhancer();
        $error = $enhancer->enhance(new \Error("Class 'Foo\\Bar\\RequiredTwice' not found"));

        $this->assertInstanceOf(ClassNotFoundError::class, $error);
    }
}
