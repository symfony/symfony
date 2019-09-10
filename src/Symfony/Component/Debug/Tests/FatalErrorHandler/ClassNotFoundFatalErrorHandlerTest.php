<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests\FatalErrorHandler;

use Composer\Autoload\ClassLoader as ComposerClassLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\DebugClassLoader;
use Symfony\Component\Debug\Exception\FatalErrorException;
use Symfony\Component\Debug\FatalErrorHandler\ClassNotFoundFatalErrorHandler;

class ClassNotFoundFatalErrorHandlerTest extends TestCase
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
            }

            if ($function[0] instanceof ComposerClassLoader) {
                $function[0]->add('Symfony_Component_Debug_Tests_Fixtures', \dirname(__DIR__, 5));
                break;
            }
        }
    }

    /**
     * @dataProvider provideClassNotFoundData
     */
    public function testHandleClassNotFound($error, $translatedMessage, $autoloader = null)
    {
        if ($autoloader) {
            // Unregister all autoloaders to ensure the custom provided
            // autoloader is the only one to be used during the test run.
            $autoloaders = spl_autoload_functions();
            array_map('spl_autoload_unregister', $autoloaders);
            spl_autoload_register($autoloader);
        }

        $handler = new ClassNotFoundFatalErrorHandler();

        $exception = $handler->handleError($error, new FatalErrorException('', 0, $error['type'], $error['file'], $error['line']));

        if ($autoloader) {
            spl_autoload_unregister($autoloader);
            array_map('spl_autoload_register', $autoloaders);
        }

        $this->assertInstanceOf('Symfony\Component\Debug\Exception\ClassNotFoundException', $exception);
        $this->assertRegExp($translatedMessage, $exception->getMessage());
        $this->assertSame($error['type'], $exception->getSeverity());
        $this->assertSame($error['file'], $exception->getFile());
        $this->assertSame($error['line'], $exception->getLine());
    }

    public function provideClassNotFoundData()
    {
        $autoloader = new ComposerClassLoader();
        $autoloader->add('Symfony\Component\Debug\Exception\\', realpath(__DIR__.'/../../Exception'));
        $autoloader->add('Symfony_Component_Debug_Tests_Fixtures', realpath(__DIR__.'/../../Tests/Fixtures'));

        $debugClassLoader = new DebugClassLoader([$autoloader, 'loadClass']);

        return [
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'WhizBangFactory\' not found',
                ],
                "/^Attempted to load class \"WhizBangFactory\" from the global namespace.\nDid you forget a \"use\" statement\?$/",
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'Foo\\Bar\\WhizBangFactory\' not found',
                ],
                "/^Attempted to load class \"WhizBangFactory\" from namespace \"Foo\\\\Bar\".\nDid you forget a \"use\" statement for another namespace\?$/",
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'UndefinedFunctionException\' not found',
                ],
                "/^Attempted to load class \"UndefinedFunctionException\" from the global namespace.\nDid you forget a \"use\" statement for .*\"Symfony\\\\Component\\\\Debug\\\\Exception\\\\UndefinedFunctionException\"\?$/",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'PEARClass\' not found',
                ],
                "/^Attempted to load class \"PEARClass\" from the global namespace.\nDid you forget a \"use\" statement for \"Symfony_Component_Debug_Tests_Fixtures_PEARClass\"\?$/",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'Foo\\Bar\\UndefinedFunctionException\' not found',
                ],
                "/^Attempted to load class \"UndefinedFunctionException\" from namespace \"Foo\\\\Bar\".\nDid you forget a \"use\" statement for .*\"Symfony\\\\Component\\\\Debug\\\\Exception\\\\UndefinedFunctionException\"\?$/",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'Foo\\Bar\\UndefinedFunctionException\' not found',
                ],
                "/^Attempted to load class \"UndefinedFunctionException\" from namespace \"Foo\\\\Bar\".\nDid you forget a \"use\" statement for \"Symfony\\\\Component\\\\Debug\\\\Exception\\\\UndefinedFunctionException\"\?$/",
                [$autoloader, 'loadClass'],
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'Foo\\Bar\\UndefinedFunctionException\' not found',
                ],
                "/^Attempted to load class \"UndefinedFunctionException\" from namespace \"Foo\\\\Bar\".\nDid you forget a \"use\" statement for \"Symfony\\\\Component\\\\Debug\\\\Exception\\\\UndefinedFunctionException\"\?/",
                [$debugClassLoader, 'loadClass'],
            ],
            [
                [
                    'type' => 1,
                    'line' => 12,
                    'file' => 'foo.php',
                    'message' => 'Class \'Foo\\Bar\\UndefinedFunctionException\' not found',
                ],
                "/^Attempted to load class \"UndefinedFunctionException\" from namespace \"Foo\\\\Bar\".\nDid you forget a \"use\" statement for another namespace\?$/",
                function ($className) { /* do nothing here */ },
            ],
        ];
    }

    public function testCannotRedeclareClass()
    {
        if (!file_exists(__DIR__.'/../FIXTURES2/REQUIREDTWICE.PHP')) {
            $this->markTestSkipped('Can only be run on case insensitive filesystems');
        }

        require_once __DIR__.'/../FIXTURES2/REQUIREDTWICE.PHP';

        $error = [
            'type' => 1,
            'line' => 12,
            'file' => 'foo.php',
            'message' => 'Class \'Foo\\Bar\\RequiredTwice\' not found',
        ];

        $handler = new ClassNotFoundFatalErrorHandler();
        $exception = $handler->handleError($error, new FatalErrorException('', 0, $error['type'], $error['file'], $error['line']));

        $this->assertInstanceOf('Symfony\Component\Debug\Exception\ClassNotFoundException', $exception);
    }
}
