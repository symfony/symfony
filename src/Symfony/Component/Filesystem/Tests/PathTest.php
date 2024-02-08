<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Thomas Schulz <mail@king2500.net>
 * @author Théo Fidry <theo.fidry@gmail.com>
 */
class PathTest extends TestCase
{
    protected array $storedEnv = [];

    protected function setUp(): void
    {
        $this->storedEnv['HOME'] = getenv('HOME');
        $this->storedEnv['HOMEDRIVE'] = getenv('HOMEDRIVE');
        $this->storedEnv['HOMEPATH'] = getenv('HOMEPATH');

        putenv('HOME=/home/webmozart');
        putenv('HOMEDRIVE=');
        putenv('HOMEPATH=');
    }

    protected function tearDown(): void
    {
        putenv('HOME='.$this->storedEnv['HOME']);
        putenv('HOMEDRIVE='.$this->storedEnv['HOMEDRIVE']);
        putenv('HOMEPATH='.$this->storedEnv['HOMEPATH']);
    }

    public static function provideCanonicalizationTests(): \Generator
    {
        // relative paths (forward slash)
        yield ['css/./style.css', 'css/style.css'];
        yield ['css/../style.css', 'style.css'];
        yield ['css/./../style.css', 'style.css'];
        yield ['css/.././style.css', 'style.css'];
        yield ['css/../../style.css', '../style.css'];
        yield ['./css/style.css', 'css/style.css'];
        yield ['../css/style.css', '../css/style.css'];
        yield ['./../css/style.css', '../css/style.css'];
        yield ['.././css/style.css', '../css/style.css'];
        yield ['../../css/style.css', '../../css/style.css'];
        yield ['', ''];
        yield ['.', ''];
        yield ['..', '..'];
        yield ['./..', '..'];
        yield ['../.', '..'];
        yield ['../..', '../..'];

        // relative paths (backslash)
        yield ['css\\.\\style.css', 'css/style.css'];
        yield ['css\\..\\style.css', 'style.css'];
        yield ['css\\.\\..\\style.css', 'style.css'];
        yield ['css\\..\\.\\style.css', 'style.css'];
        yield ['css\\..\\..\\style.css', '../style.css'];
        yield ['.\\css\\style.css', 'css/style.css'];
        yield ['..\\css\\style.css', '../css/style.css'];
        yield ['.\\..\\css\\style.css', '../css/style.css'];
        yield ['..\\.\\css\\style.css', '../css/style.css'];
        yield ['..\\..\\css\\style.css', '../../css/style.css'];

        // absolute paths (forward slash, UNIX)
        yield ['/css/style.css', '/css/style.css'];
        yield ['/css/./style.css', '/css/style.css'];
        yield ['/css/../style.css', '/style.css'];
        yield ['/css/./../style.css', '/style.css'];
        yield ['/css/.././style.css', '/style.css'];
        yield ['/./css/style.css', '/css/style.css'];
        yield ['/../css/style.css', '/css/style.css'];
        yield ['/./../css/style.css', '/css/style.css'];
        yield ['/.././css/style.css', '/css/style.css'];
        yield ['/../../css/style.css', '/css/style.css'];

        // absolute paths (backslash, UNIX)
        yield ['\\css\\style.css', '/css/style.css'];
        yield ['\\css\\.\\style.css', '/css/style.css'];
        yield ['\\css\\..\\style.css', '/style.css'];
        yield ['\\css\\.\\..\\style.css', '/style.css'];
        yield ['\\css\\..\\.\\style.css', '/style.css'];
        yield ['\\.\\css\\style.css', '/css/style.css'];
        yield ['\\..\\css\\style.css', '/css/style.css'];
        yield ['\\.\\..\\css\\style.css', '/css/style.css'];
        yield ['\\..\\.\\css\\style.css', '/css/style.css'];
        yield ['\\..\\..\\css\\style.css', '/css/style.css'];

        // absolute paths (forward slash, Windows)
        yield ['C:/css/style.css', 'C:/css/style.css'];
        yield ['C:/css/./style.css', 'C:/css/style.css'];
        yield ['C:/css/../style.css', 'C:/style.css'];
        yield ['C:/css/./../style.css', 'C:/style.css'];
        yield ['C:/css/.././style.css', 'C:/style.css'];
        yield ['C:/./css/style.css', 'C:/css/style.css'];
        yield ['C:/../css/style.css', 'C:/css/style.css'];
        yield ['C:/./../css/style.css', 'C:/css/style.css'];
        yield ['C:/.././css/style.css', 'C:/css/style.css'];
        yield ['C:/../../css/style.css', 'C:/css/style.css'];

        // absolute paths (backslash, Windows)
        yield ['C:\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\css\\.\\style.css', 'C:/css/style.css'];
        yield ['C:\\css\\..\\style.css', 'C:/style.css'];
        yield ['C:\\css\\.\\..\\style.css', 'C:/style.css'];
        yield ['C:\\css\\..\\.\\style.css', 'C:/style.css'];
        yield ['C:\\.\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\.\\..\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\.\\css\\style.css', 'C:/css/style.css'];
        yield ['C:\\..\\..\\css\\style.css', 'C:/css/style.css'];

        // Windows special case
        yield ['C:', 'C:/'];

        // Don't change malformed path
        yield ['C:css/style.css', 'C:css/style.css'];

        // absolute paths (stream, UNIX)
        yield ['phar:///css/style.css', 'phar:///css/style.css'];
        yield ['phar:///css/./style.css', 'phar:///css/style.css'];
        yield ['phar:///css/../style.css', 'phar:///style.css'];
        yield ['phar:///css/./../style.css', 'phar:///style.css'];
        yield ['phar:///css/.././style.css', 'phar:///style.css'];
        yield ['phar:///./css/style.css', 'phar:///css/style.css'];
        yield ['phar:///../css/style.css', 'phar:///css/style.css'];
        yield ['phar:///./../css/style.css', 'phar:///css/style.css'];
        yield ['phar:///.././css/style.css', 'phar:///css/style.css'];
        yield ['phar:///../../css/style.css', 'phar:///css/style.css'];

        // absolute paths (stream, Windows)
        yield ['phar://C:/css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/css/./style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/css/../style.css', 'phar://C:/style.css'];
        yield ['phar://C:/css/./../style.css', 'phar://C:/style.css'];
        yield ['phar://C:/css/.././style.css', 'phar://C:/style.css'];
        yield ['phar://C:/./css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/../css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/./../css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/.././css/style.css', 'phar://C:/css/style.css'];
        yield ['phar://C:/../../css/style.css', 'phar://C:/css/style.css'];

        // paths with "~" UNIX
        yield ['~/css/style.css', '/home/webmozart/css/style.css'];
        yield ['~/css/./style.css', '/home/webmozart/css/style.css'];
        yield ['~/css/../style.css', '/home/webmozart/style.css'];
        yield ['~/css/./../style.css', '/home/webmozart/style.css'];
        yield ['~/css/.././style.css', '/home/webmozart/style.css'];
        yield ['~/./css/style.css', '/home/webmozart/css/style.css'];
        yield ['~/../css/style.css', '/home/css/style.css'];
        yield ['~/./../css/style.css', '/home/css/style.css'];
        yield ['~/.././css/style.css', '/home/css/style.css'];
        yield ['~/../../css/style.css', '/css/style.css'];
    }

    /**
     * @dataProvider provideCanonicalizationTests
     */
    public function testCanonicalize(string $path, string $canonicalized)
    {
        $this->assertSame($canonicalized, Path::canonicalize($path));
    }

    public static function provideGetDirectoryTests(): \Generator
    {
        yield ['/webmozart/symfony/style.css', '/webmozart/symfony'];
        yield ['/webmozart/symfony', '/webmozart'];
        yield ['/webmozart', '/'];
        yield ['/', '/'];
        yield ['', ''];

        yield ['\\webmozart\\symfony\\style.css', '/webmozart/symfony'];
        yield ['\\webmozart\\symfony', '/webmozart'];
        yield ['\\webmozart', '/'];
        yield ['\\', '/'];

        yield ['C:/webmozart/symfony/style.css', 'C:/webmozart/symfony'];
        yield ['C:/webmozart/symfony', 'C:/webmozart'];
        yield ['C:/webmozart', 'C:/'];
        yield ['C:/', 'C:/'];
        yield ['C:', 'C:/'];

        yield ['C:\\webmozart\\symfony\\style.css', 'C:/webmozart/symfony'];
        yield ['C:\\webmozart\\symfony', 'C:/webmozart'];
        yield ['C:\\webmozart', 'C:/'];
        yield ['C:\\', 'C:/'];

        yield ['phar:///webmozart/symfony/style.css', 'phar:///webmozart/symfony'];
        yield ['phar:///webmozart/symfony', 'phar:///webmozart'];
        yield ['phar:///webmozart', 'phar:///'];
        yield ['phar:///', 'phar:///'];

        yield ['phar://C:/webmozart/symfony/style.css', 'phar://C:/webmozart/symfony'];
        yield ['phar://C:/webmozart/symfony', 'phar://C:/webmozart'];
        yield ['phar://C:/webmozart', 'phar://C:/'];
        yield ['phar://C:/', 'phar://C:/'];

        yield ['webmozart/symfony/style.css', 'webmozart/symfony'];
        yield ['webmozart/symfony', 'webmozart'];
        yield ['webmozart', ''];

        yield ['webmozart\\symfony\\style.css', 'webmozart/symfony'];
        yield ['webmozart\\symfony', 'webmozart'];
        yield ['webmozart', ''];

        yield ['/webmozart/./symfony/style.css', '/webmozart/symfony'];
        yield ['/webmozart/../symfony/style.css', '/symfony'];
        yield ['/webmozart/./../symfony/style.css', '/symfony'];
        yield ['/webmozart/.././symfony/style.css', '/symfony'];
        yield ['/webmozart/../../symfony/style.css', '/symfony'];
        yield ['/.', '/'];
        yield ['/..', '/'];

        yield ['C:webmozart', ''];

        yield ['D:/Folder/Aééé/Subfolder', 'D:/Folder/Aééé'];
    }

    /**
     * @dataProvider provideGetDirectoryTests
     */
    public function testGetDirectory(string $path, string $directory)
    {
        $this->assertSame($directory, Path::getDirectory($path));
    }

    public static function provideGetFilenameWithoutExtensionTests(): \Generator
    {
        yield ['/webmozart/symfony/style.css.twig', null, 'style.css'];
        yield ['/webmozart/symfony/style.css.', null, 'style.css'];
        yield ['/webmozart/symfony/style.css', null, 'style'];
        yield ['/webmozart/symfony/.style.css', null, '.style'];
        yield ['/webmozart/symfony/', null, 'symfony'];
        yield ['/webmozart/symfony', null, 'symfony'];
        yield ['/', null, ''];
        yield ['', null, ''];

        yield ['/webmozart/symfony/style.css', 'css', 'style'];
        yield ['/webmozart/symfony/style.css', '.css', 'style'];
        yield ['/webmozart/symfony/style.css', 'twig', 'style.css'];
        yield ['/webmozart/symfony/style.css', '.twig', 'style.css'];
        yield ['/webmozart/symfony/style.css', '', 'style.css'];
        yield ['/webmozart/symfony/style.css.', '', 'style.css'];
        yield ['/webmozart/symfony/style.css.', '.', 'style.css'];
        yield ['/webmozart/symfony/style.css.', '.css', 'style.css'];
        yield ['/webmozart/symfony/.style.css', 'css', '.style'];
        yield ['/webmozart/symfony/.style.css', '.css', '.style'];
    }

    /**
     * @dataProvider provideGetFilenameWithoutExtensionTests
     */
    public function testGetFilenameWithoutExtension(string $path, ?string $extension, string $filename)
    {
        $this->assertSame($filename, Path::getFilenameWithoutExtension($path, $extension));
    }

    public static function provideGetExtensionTests(): \Generator
    {
        yield ['/webmozart/symfony/style.css.twig', false, 'twig'];
        yield ['/webmozart/symfony/style.css', false, 'css'];
        yield ['/webmozart/symfony/style.css.', false, ''];
        yield ['/webmozart/symfony/', false, ''];
        yield ['/webmozart/symfony', false, ''];
        yield ['/', false, ''];
        yield ['', false, ''];

        yield ['/webmozart/symfony/style.CSS', false, 'CSS'];
        yield ['/webmozart/symfony/style.CSS', true, 'css'];
        yield ['/webmozart/symfony/style.ÄÖÜ', false, 'ÄÖÜ'];

        yield ['/webmozart/symfony/style.ÄÖÜ', true, 'äöü'];
    }

    /**
     * @dataProvider provideGetExtensionTests
     */
    public function testGetExtension(string $path, bool $forceLowerCase, string $extension)
    {
        $this->assertSame($extension, Path::getExtension($path, $forceLowerCase));
    }

    public static function provideHasExtensionTests(): \Generator
    {
        yield [true, '/webmozart/symfony/style.css.twig', null, false];
        yield [true, '/webmozart/symfony/style.css', null, false];
        yield [false, '/webmozart/symfony/style.css.', null, false];
        yield [false, '/webmozart/symfony/', null, false];
        yield [false, '/webmozart/symfony', null, false];
        yield [false, '/', null, false];
        yield [false, '', null, false];

        yield [true, '/webmozart/symfony/style.css.twig', 'twig', false];
        yield [false, '/webmozart/symfony/style.css.twig', 'css', false];
        yield [true, '/webmozart/symfony/style.css', 'css', false];
        yield [true, '/webmozart/symfony/style.css', '.css', false];
        yield [true, '/webmozart/symfony/style.css.', '', false];
        yield [false, '/webmozart/symfony/', 'ext', false];
        yield [false, '/webmozart/symfony', 'ext', false];
        yield [false, '/', 'ext', false];
        yield [false, '', 'ext', false];

        yield [false, '/webmozart/symfony/style.css', 'CSS', false];
        yield [true, '/webmozart/symfony/style.css', 'CSS', true];
        yield [false, '/webmozart/symfony/style.CSS', 'css', false];
        yield [true, '/webmozart/symfony/style.CSS', 'css', true];
        yield [true, '/webmozart/symfony/style.ÄÖÜ', 'ÄÖÜ', false];

        yield [true, '/webmozart/symfony/style.css', ['ext', 'css'], false];
        yield [true, '/webmozart/symfony/style.css', ['.ext', '.css'], false];
        yield [true, '/webmozart/symfony/style.css.', ['ext', ''], false];
        yield [false, '/webmozart/symfony/style.css', ['foo', 'bar', ''], false];
        yield [false, '/webmozart/symfony/style.css', ['.foo', '.bar', ''], false];

        // This can only be tested, when mbstring is installed
        yield [true, '/webmozart/symfony/style.ÄÖÜ', 'äöü', true];
        yield [true, '/webmozart/symfony/style.ÄÖÜ', ['äöü'], true];
    }

    /**
     * @dataProvider provideHasExtensionTests
     *
     * @param string|string[]|null $extension
     */
    public function testHasExtension(bool $hasExtension, string $path, $extension, bool $ignoreCase)
    {
        $this->assertSame($hasExtension, Path::hasExtension($path, $extension, $ignoreCase));
    }

    public static function provideChangeExtensionTests(): \Generator
    {
        yield ['/webmozart/symfony/style.css.twig', 'html', '/webmozart/symfony/style.css.html'];
        yield ['/webmozart/symfony/style.css', 'sass', '/webmozart/symfony/style.sass'];
        yield ['/webmozart/symfony/style.css', '.sass', '/webmozart/symfony/style.sass'];
        yield ['/webmozart/symfony/style.css', '', '/webmozart/symfony/style.'];
        yield ['/webmozart/symfony/style.css.', 'twig', '/webmozart/symfony/style.css.twig'];
        yield ['/webmozart/symfony/style.css.', '', '/webmozart/symfony/style.css.'];
        yield ['/webmozart/symfony/style.css', 'äöü', '/webmozart/symfony/style.äöü'];
        yield ['/webmozart/symfony/style.äöü', 'css', '/webmozart/symfony/style.css'];
        yield ['/webmozart/symfony/', 'css', '/webmozart/symfony/'];
        yield ['/webmozart/symfony', 'css', '/webmozart/symfony.css'];
        yield ['/', 'css', '/'];
        yield ['', 'css', ''];
    }

    /**
     * @dataProvider provideChangeExtensionTests
     */
    public function testChangeExtension(string $path, string $extension, string $pathExpected)
    {
        $this->assertSame($pathExpected, Path::changeExtension($path, $extension));
    }

    public static function provideIsAbsolutePathTests(): \Generator
    {
        yield ['/css/style.css', true];
        yield ['/', true];
        yield ['css/style.css', false];
        yield ['', false];

        yield ['\\css\\style.css', true];
        yield ['\\', true];
        yield ['css\\style.css', false];

        yield ['C:/css/style.css', true];
        yield ['D:/', true];

        yield ['E:\\css\\style.css', true];
        yield ['F:\\', true];

        yield ['phar:///css/style.css', true];
        yield ['phar:///', true];

        // Windows special case
        yield ['C:', true];

        // Not considered absolute
        yield ['C:css/style.css', false];
    }

    /**
     * @dataProvider provideIsAbsolutePathTests
     */
    public function testIsAbsolute(string $path, bool $isAbsolute)
    {
        $this->assertSame($isAbsolute, Path::isAbsolute($path));
    }

    /**
     * @dataProvider provideIsAbsolutePathTests
     */
    public function testIsRelative(string $path, bool $isAbsolute)
    {
        $this->assertSame(!$isAbsolute, Path::isRelative($path));
    }

    public static function provideGetRootTests(): \Generator
    {
        yield ['/css/style.css', '/'];
        yield ['/', '/'];
        yield ['css/style.css', ''];
        yield ['', ''];

        yield ['\\css\\style.css', '/'];
        yield ['\\', '/'];
        yield ['css\\style.css', ''];

        yield ['C:/css/style.css', 'C:/'];
        yield ['C:/', 'C:/'];
        yield ['C:', 'C:/'];

        yield ['D:\\css\\style.css', 'D:/'];
        yield ['D:\\', 'D:/'];

        yield ['phar:///css/style.css', 'phar:///'];
        yield ['phar:///', 'phar:///'];

        yield ['phar://C:/css/style.css', 'phar://C:/'];
        yield ['phar://C:/', 'phar://C:/'];
        yield ['phar://C:', 'phar://C:/'];
    }

    /**
     * @dataProvider provideGetRootTests
     */
    public function testGetRoot(string $path, string $root)
    {
        $this->assertSame($root, Path::getRoot($path));
    }

    private static function getPathTests(): \Generator
    {
        yield from [
            // relative to absolute path
            ['css/style.css', '/webmozart/symfony', '/webmozart/symfony/css/style.css'],
            ['../css/style.css', '/webmozart/symfony', '/webmozart/css/style.css'],
            ['../../css/style.css', '/webmozart/symfony', '/css/style.css'],

            // relative to root
            ['css/style.css', '/', '/css/style.css'],
            ['css/style.css', 'C:', 'C:/css/style.css'],
            ['css/style.css', 'C:/', 'C:/css/style.css'],

            // same sub directories in different base directories
            ['../../symfony/css/style.css', '/webmozart/css', '/symfony/css/style.css'],

            ['', '/webmozart/symfony', '/webmozart/symfony'],
            ['..', '/webmozart/symfony', '/webmozart'],
        ];
    }

    public static function provideMakeAbsoluteTests(): \Generator
    {
        yield from self::getPathTests();

        // collapse dots
        yield ['css/./style.css', '/webmozart/symfony', '/webmozart/symfony/css/style.css'];
        yield ['css/../style.css', '/webmozart/symfony', '/webmozart/symfony/style.css'];
        yield ['css/./../style.css', '/webmozart/symfony', '/webmozart/symfony/style.css'];
        yield ['css/.././style.css', '/webmozart/symfony', '/webmozart/symfony/style.css'];
        yield ['./css/style.css', '/webmozart/symfony', '/webmozart/symfony/css/style.css'];

        yield ['css\\.\\style.css', '\\webmozart\\symfony', '/webmozart/symfony/css/style.css'];
        yield ['css\\..\\style.css', '\\webmozart\\symfony', '/webmozart/symfony/style.css'];
        yield ['css\\.\\..\\style.css', '\\webmozart\\symfony', '/webmozart/symfony/style.css'];
        yield ['css\\..\\.\\style.css', '\\webmozart\\symfony', '/webmozart/symfony/style.css'];
        yield ['.\\css\\style.css', '\\webmozart\\symfony', '/webmozart/symfony/css/style.css'];

        // collapse dots on root
        yield ['./css/style.css', '/', '/css/style.css'];
        yield ['../css/style.css', '/', '/css/style.css'];
        yield ['../css/./style.css', '/', '/css/style.css'];
        yield ['../css/../style.css', '/', '/style.css'];
        yield ['../css/./../style.css', '/', '/style.css'];
        yield ['../css/.././style.css', '/', '/style.css'];

        yield ['.\\css\\style.css', '\\', '/css/style.css'];
        yield ['..\\css\\style.css', '\\', '/css/style.css'];
        yield ['..\\css\\.\\style.css', '\\', '/css/style.css'];
        yield ['..\\css\\..\\style.css', '\\', '/style.css'];
        yield ['..\\css\\.\\..\\style.css', '\\', '/style.css'];
        yield ['..\\css\\..\\.\\style.css', '\\', '/style.css'];

        yield ['./css/style.css', 'C:/', 'C:/css/style.css'];
        yield ['../css/style.css', 'C:/', 'C:/css/style.css'];
        yield ['../css/./style.css', 'C:/', 'C:/css/style.css'];
        yield ['../css/../style.css', 'C:/', 'C:/style.css'];
        yield ['../css/./../style.css', 'C:/', 'C:/style.css'];
        yield ['../css/.././style.css', 'C:/', 'C:/style.css'];

        yield ['.\\css\\style.css', 'C:\\', 'C:/css/style.css'];
        yield ['..\\css\\style.css', 'C:\\', 'C:/css/style.css'];
        yield ['..\\css\\.\\style.css', 'C:\\', 'C:/css/style.css'];
        yield ['..\\css\\..\\style.css', 'C:\\', 'C:/style.css'];
        yield ['..\\css\\.\\..\\style.css', 'C:\\', 'C:/style.css'];
        yield ['..\\css\\..\\.\\style.css', 'C:\\', 'C:/style.css'];

        yield ['./css/style.css', 'phar:///', 'phar:///css/style.css'];
        yield ['../css/style.css', 'phar:///', 'phar:///css/style.css'];
        yield ['../css/./style.css', 'phar:///', 'phar:///css/style.css'];
        yield ['../css/../style.css', 'phar:///', 'phar:///style.css'];
        yield ['../css/./../style.css', 'phar:///', 'phar:///style.css'];
        yield ['../css/.././style.css', 'phar:///', 'phar:///style.css'];

        yield ['./css/style.css', 'phar://C:/', 'phar://C:/css/style.css'];
        yield ['../css/style.css', 'phar://C:/', 'phar://C:/css/style.css'];
        yield ['../css/./style.css', 'phar://C:/', 'phar://C:/css/style.css'];
        yield ['../css/../style.css', 'phar://C:/', 'phar://C:/style.css'];
        yield ['../css/./../style.css', 'phar://C:/', 'phar://C:/style.css'];
        yield ['../css/.././style.css', 'phar://C:/', 'phar://C:/style.css'];

        // absolute paths
        yield ['/css/style.css', '/webmozart/symfony', '/css/style.css'];
        yield ['\\css\\style.css', '/webmozart/symfony', '/css/style.css'];
        yield ['C:/css/style.css', 'C:/webmozart/symfony', 'C:/css/style.css'];
        yield ['D:\\css\\style.css', 'D:/webmozart/symfony', 'D:/css/style.css'];
    }

    /**
     * @dataProvider provideMakeAbsoluteTests
     */
    public function testMakeAbsolute(string $relativePath, string $basePath, string $absolutePath)
    {
        $this->assertSame($absolutePath, Path::makeAbsolute($relativePath, $basePath));
    }

    public function testMakeAbsoluteFailsIfBasePathNotAbsolute()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The base path "webmozart/symfony" is not an absolute path.');

        Path::makeAbsolute('css/style.css', 'webmozart/symfony');
    }

    public function testMakeAbsoluteFailsIfBasePathEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The base path must be a non-empty string. Got: ""');

        Path::makeAbsolute('css/style.css', '');
    }

    public static function provideAbsolutePathsWithDifferentRoots(): \Generator
    {
        yield ['C:/css/style.css', '/webmozart/symfony'];
        yield ['C:/css/style.css', '\\webmozart\\symfony'];
        yield ['C:\\css\\style.css', '/webmozart/symfony'];
        yield ['C:\\css\\style.css', '\\webmozart\\symfony'];

        yield ['/css/style.css', 'C:/webmozart/symfony'];
        yield ['/css/style.css', 'C:\\webmozart\\symfony'];
        yield ['\\css\\style.css', 'C:/webmozart/symfony'];
        yield ['\\css\\style.css', 'C:\\webmozart\\symfony'];

        yield ['D:/css/style.css', 'C:/webmozart/symfony'];
        yield ['D:/css/style.css', 'C:\\webmozart\\symfony'];
        yield ['D:\\css\\style.css', 'C:/webmozart/symfony'];
        yield ['D:\\css\\style.css', 'C:\\webmozart\\symfony'];

        yield ['phar:///css/style.css', '/webmozart/symfony'];
        yield ['/css/style.css', 'phar:///webmozart/symfony'];

        yield ['phar://C:/css/style.css', 'C:/webmozart/symfony'];
        yield ['phar://C:/css/style.css', 'C:\\webmozart\\symfony'];
        yield ['phar://C:\\css\\style.css', 'C:/webmozart/symfony'];
        yield ['phar://C:\\css\\style.css', 'C:\\webmozart\\symfony'];
    }

    /**
     * @dataProvider provideAbsolutePathsWithDifferentRoots
     */
    public function testMakeAbsoluteDoesNotFailIfDifferentRoot(string $basePath, string $absolutePath)
    {
        // If a path in partition D: is passed, but $basePath is in partition
        // C:, the path should be returned unchanged
        $this->assertSame(Path::canonicalize($absolutePath), Path::makeAbsolute($absolutePath, $basePath));
    }

    public static function provideMakeRelativeTests(): \Generator
    {
        foreach (self::getPathTests() as $set) {
            yield [$set[2], $set[1], $set[0]];
        }

        yield ['/webmozart/symfony/./css/style.css', '/webmozart/symfony', 'css/style.css'];
        yield ['/webmozart/symfony/../css/style.css', '/webmozart/symfony', '../css/style.css'];
        yield ['/webmozart/symfony/.././css/style.css', '/webmozart/symfony', '../css/style.css'];
        yield ['/webmozart/symfony/./../css/style.css', '/webmozart/symfony', '../css/style.css'];
        yield ['/webmozart/symfony/../../css/style.css', '/webmozart/symfony', '../../css/style.css'];
        yield ['/webmozart/symfony/css/style.css', '/webmozart/./symfony', 'css/style.css'];
        yield ['/webmozart/symfony/css/style.css', '/webmozart/../symfony', '../webmozart/symfony/css/style.css'];
        yield ['/webmozart/symfony/css/style.css', '/webmozart/./../symfony', '../webmozart/symfony/css/style.css'];
        yield ['/webmozart/symfony/css/style.css', '/webmozart/.././symfony', '../webmozart/symfony/css/style.css'];
        yield ['/webmozart/symfony/css/style.css', '/webmozart/../../symfony', '../webmozart/symfony/css/style.css'];

        // first argument shorter than second
        yield ['/css', '/webmozart/symfony', '../../css'];

        // second argument shorter than first
        yield ['/webmozart/symfony', '/css', '../webmozart/symfony'];

        yield ['\\webmozart\\symfony\\css\\style.css', '\\webmozart\\symfony', 'css/style.css'];
        yield ['\\webmozart\\css\\style.css', '\\webmozart\\symfony', '../css/style.css'];
        yield ['\\css\\style.css', '\\webmozart\\symfony', '../../css/style.css'];

        yield ['C:/webmozart/symfony/css/style.css', 'C:/webmozart/symfony', 'css/style.css'];
        yield ['C:/webmozart/css/style.css', 'C:/webmozart/symfony', '../css/style.css'];
        yield ['C:/css/style.css', 'C:/webmozart/symfony', '../../css/style.css'];

        yield ['C:\\webmozart\\symfony\\css\\style.css', 'C:\\webmozart\\symfony', 'css/style.css'];
        yield ['C:\\webmozart\\css\\style.css', 'C:\\webmozart\\symfony', '../css/style.css'];
        yield ['C:\\css\\style.css', 'C:\\webmozart\\symfony', '../../css/style.css'];

        yield ['phar:///webmozart/symfony/css/style.css', 'phar:///webmozart/symfony', 'css/style.css'];
        yield ['phar:///webmozart/css/style.css', 'phar:///webmozart/symfony', '../css/style.css'];
        yield ['phar:///css/style.css', 'phar:///webmozart/symfony', '../../css/style.css'];

        yield ['phar://C:/webmozart/symfony/css/style.css', 'phar://C:/webmozart/symfony', 'css/style.css'];
        yield ['phar://C:/webmozart/css/style.css', 'phar://C:/webmozart/symfony', '../css/style.css'];
        yield ['phar://C:/css/style.css', 'phar://C:/webmozart/symfony', '../../css/style.css'];

        // already relative + already in root basepath
        yield ['../style.css', '/', 'style.css'];
        yield ['./style.css', '/', 'style.css'];
        yield ['../../style.css', '/', 'style.css'];
        yield ['..\\style.css', 'C:\\', 'style.css'];
        yield ['.\\style.css', 'C:\\', 'style.css'];
        yield ['..\\..\\style.css', 'C:\\', 'style.css'];
        yield ['../style.css', 'C:/', 'style.css'];
        yield ['./style.css', 'C:/', 'style.css'];
        yield ['../../style.css', 'C:/', 'style.css'];
        yield ['..\\style.css', '\\', 'style.css'];
        yield ['.\\style.css', '\\', 'style.css'];
        yield ['..\\..\\style.css', '\\', 'style.css'];
        yield ['../style.css', 'phar:///', 'style.css'];
        yield ['./style.css', 'phar:///', 'style.css'];
        yield ['../../style.css', 'phar:///', 'style.css'];
        yield ['..\\style.css', 'phar://C:\\', 'style.css'];
        yield ['.\\style.css', 'phar://C:\\', 'style.css'];
        yield ['..\\..\\style.css', 'phar://C:\\', 'style.css'];

        yield ['css/../style.css', '/', 'style.css'];
        yield ['css/./style.css', '/', 'css/style.css'];
        yield ['css\\..\\style.css', 'C:\\', 'style.css'];
        yield ['css\\.\\style.css', 'C:\\', 'css/style.css'];
        yield ['css/../style.css', 'C:/', 'style.css'];
        yield ['css/./style.css', 'C:/', 'css/style.css'];
        yield ['css\\..\\style.css', '\\', 'style.css'];
        yield ['css\\.\\style.css', '\\', 'css/style.css'];
        yield ['css/../style.css', 'phar:///', 'style.css'];
        yield ['css/./style.css', 'phar:///', 'css/style.css'];
        yield ['css\\..\\style.css', 'phar://C:\\', 'style.css'];
        yield ['css\\.\\style.css', 'phar://C:\\', 'css/style.css'];

        // already relative
        yield ['css/style.css', '/webmozart/symfony', 'css/style.css'];
        yield ['css\\style.css', '\\webmozart\\symfony', 'css/style.css'];

        // both relative
        yield ['css/style.css', 'webmozart/symfony', '../../css/style.css'];
        yield ['css\\style.css', 'webmozart\\symfony', '../../css/style.css'];

        // relative to empty
        yield ['css/style.css', '', 'css/style.css'];
        yield ['css\\style.css', '', 'css/style.css'];

        // different slashes in path and base path
        yield ['/webmozart/symfony/css/style.css', '\\webmozart\\symfony', 'css/style.css'];
        yield ['\\webmozart\\symfony\\css\\style.css', '/webmozart/symfony', 'css/style.css'];
    }

    /**
     * @dataProvider provideMakeRelativeTests
     */
    public function testMakeRelative(string $absolutePath, string $basePath, string $relativePath)
    {
        $this->assertSame($relativePath, Path::makeRelative($absolutePath, $basePath));
    }

    public function testMakeRelativeFailsIfAbsolutePathAndBasePathNotAbsolute()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The absolute path "/webmozart/symfony/css/style.css" cannot be made relative to the relative path "webmozart/symfony". You should provide an absolute base path instead.');

        Path::makeRelative('/webmozart/symfony/css/style.css', 'webmozart/symfony');
    }

    public function testMakeRelativeFailsIfAbsolutePathAndBasePathEmpty()
    {
        $this->expectExceptionMessage('The absolute path "/webmozart/symfony/css/style.css" cannot be made relative to the relative path "". You should provide an absolute base path instead.');

        Path::makeRelative('/webmozart/symfony/css/style.css', '');
    }

    /**
     * @dataProvider provideAbsolutePathsWithDifferentRoots
     */
    public function testMakeRelativeFailsIfDifferentRoot(string $absolutePath, string $basePath)
    {
        $this->expectException(\InvalidArgumentException::class);

        Path::makeRelative($absolutePath, $basePath);
    }

    public static function provideIsLocalTests(): \Generator
    {
        yield ['/bg.png', true];
        yield ['bg.png', true];
        yield ['http://example.com/bg.png', false];
        yield ['http://example.com', false];
        yield ['', false];
    }

    /**
     * @dataProvider provideIsLocalTests
     */
    public function testIsLocal(string $path, bool $isLocal)
    {
        $this->assertSame($isLocal, Path::isLocal($path));
    }

    public static function provideGetLongestCommonBasePathTests(): \Generator
    {
        // same paths
        yield [['/base/path', '/base/path'], '/base/path'];
        yield [['C:/base/path', 'C:/base/path'], 'C:/base/path'];
        yield [['C:\\base\\path', 'C:\\base\\path'], 'C:/base/path'];
        yield [['C:/base/path', 'C:\\base\\path'], 'C:/base/path'];
        yield [['phar:///base/path', 'phar:///base/path'], 'phar:///base/path'];
        yield [['phar://C:/base/path', 'phar://C:/base/path'], 'phar://C:/base/path'];

        // trailing slash
        yield [['/base/path/', '/base/path'], '/base/path'];
        yield [['C:/base/path/', 'C:/base/path'], 'C:/base/path'];
        yield [['C:\\base\\path\\', 'C:\\base\\path'], 'C:/base/path'];
        yield [['C:/base/path/', 'C:\\base\\path'], 'C:/base/path'];
        yield [['phar:///base/path/', 'phar:///base/path'], 'phar:///base/path'];
        yield [['phar://C:/base/path/', 'phar://C:/base/path'], 'phar://C:/base/path'];

        yield [['/base/path', '/base/path/'], '/base/path'];
        yield [['C:/base/path', 'C:/base/path/'], 'C:/base/path'];
        yield [['C:\\base\\path', 'C:\\base\\path\\'], 'C:/base/path'];
        yield [['C:/base/path', 'C:\\base\\path\\'], 'C:/base/path'];
        yield [['phar:///base/path', 'phar:///base/path/'], 'phar:///base/path'];
        yield [['phar://C:/base/path', 'phar://C:/base/path/'], 'phar://C:/base/path'];

        // first in second
        yield [['/base/path/sub', '/base/path'], '/base/path'];
        yield [['C:/base/path/sub', 'C:/base/path'], 'C:/base/path'];
        yield [['C:\\base\\path\\sub', 'C:\\base\\path'], 'C:/base/path'];
        yield [['C:/base/path/sub', 'C:\\base\\path'], 'C:/base/path'];
        yield [['phar:///base/path/sub', 'phar:///base/path'], 'phar:///base/path'];
        yield [['phar://C:/base/path/sub', 'phar://C:/base/path'], 'phar://C:/base/path'];

        // second in first
        yield [['/base/path', '/base/path/sub'], '/base/path'];
        yield [['C:/base/path', 'C:/base/path/sub'], 'C:/base/path'];
        yield [['C:\\base\\path', 'C:\\base\\path\\sub'], 'C:/base/path'];
        yield [['C:/base/path', 'C:\\base\\path\\sub'], 'C:/base/path'];
        yield [['phar:///base/path', 'phar:///base/path/sub'], 'phar:///base/path'];
        yield [['phar://C:/base/path', 'phar://C:/base/path/sub'], 'phar://C:/base/path'];

        // first is prefix
        yield [['/base/path/di', '/base/path/dir'], '/base/path'];
        yield [['C:/base/path/di', 'C:/base/path/dir'], 'C:/base/path'];
        yield [['C:\\base\\path\\di', 'C:\\base\\path\\dir'], 'C:/base/path'];
        yield [['C:/base/path/di', 'C:\\base\\path\\dir'], 'C:/base/path'];
        yield [['phar:///base/path/di', 'phar:///base/path/dir'], 'phar:///base/path'];
        yield [['phar://C:/base/path/di', 'phar://C:/base/path/dir'], 'phar://C:/base/path'];

        // second is prefix
        yield [['/base/path/dir', '/base/path/di'], '/base/path'];
        yield [['C:/base/path/dir', 'C:/base/path/di'], 'C:/base/path'];
        yield [['C:\\base\\path\\dir', 'C:\\base\\path\\di'], 'C:/base/path'];
        yield [['C:/base/path/dir', 'C:\\base\\path\\di'], 'C:/base/path'];
        yield [['phar:///base/path/dir', 'phar:///base/path/di'], 'phar:///base/path'];
        yield [['phar://C:/base/path/dir', 'phar://C:/base/path/di'], 'phar://C:/base/path'];

        // root is common base path
        yield [['/first', '/second'], '/'];
        yield [['C:/first', 'C:/second'], 'C:/'];
        yield [['C:\\first', 'C:\\second'], 'C:/'];
        yield [['C:/first', 'C:\\second'], 'C:/'];
        yield [['phar:///first', 'phar:///second'], 'phar:///'];
        yield [['phar://C:/first', 'phar://C:/second'], 'phar://C:/'];

        // windows vs unix
        yield [['/base/path', 'C:/base/path'], null];
        yield [['C:/base/path', '/base/path'], null];
        yield [['/base/path', 'C:\\base\\path'], null];
        yield [['phar:///base/path', 'phar://C:/base/path'], null];

        // different partitions
        yield [['C:/base/path', 'D:/base/path'], null];
        yield [['C:/base/path', 'D:\\base\\path'], null];
        yield [['C:\\base\\path', 'D:\\base\\path'], null];
        yield [['phar://C:/base/path', 'phar://D:/base/path'], null];

        // three paths
        yield [['/base/path/foo', '/base/path', '/base/path/bar'], '/base/path'];
        yield [['C:/base/path/foo', 'C:/base/path', 'C:/base/path/bar'], 'C:/base/path'];
        yield [['C:\\base\\path\\foo', 'C:\\base\\path', 'C:\\base\\path\\bar'], 'C:/base/path'];
        yield [['C:/base/path//foo', 'C:/base/path', 'C:\\base\\path\\bar'], 'C:/base/path'];
        yield [['phar:///base/path/foo', 'phar:///base/path', 'phar:///base/path/bar'], 'phar:///base/path'];
        yield [['phar://C:/base/path/foo', 'phar://C:/base/path', 'phar://C:/base/path/bar'], 'phar://C:/base/path'];

        // three paths with root
        yield [['/base/path/foo', '/', '/base/path/bar'], '/'];
        yield [['C:/base/path/foo', 'C:/', 'C:/base/path/bar'], 'C:/'];
        yield [['C:\\base\\path\\foo', 'C:\\', 'C:\\base\\path\\bar'], 'C:/'];
        yield [['C:/base/path//foo', 'C:/', 'C:\\base\\path\\bar'], 'C:/'];
        yield [['phar:///base/path/foo', 'phar:///', 'phar:///base/path/bar'], 'phar:///'];
        yield [['phar://C:/base/path/foo', 'phar://C:/', 'phar://C:/base/path/bar'], 'phar://C:/'];

        // three paths, different roots
        yield [['/base/path/foo', 'C:/base/path', '/base/path/bar'], null];
        yield [['/base/path/foo', 'C:\\base\\path', '/base/path/bar'], null];
        yield [['C:/base/path/foo', 'D:/base/path', 'C:/base/path/bar'], null];
        yield [['C:\\base\\path\\foo', 'D:\\base\\path', 'C:\\base\\path\\bar'], null];
        yield [['C:/base/path//foo', 'D:/base/path', 'C:\\base\\path\\bar'], null];
        yield [['phar:///base/path/foo', 'phar://C:/base/path', 'phar:///base/path/bar'], null];
        yield [['phar://C:/base/path/foo', 'phar://D:/base/path', 'phar://C:/base/path/bar'], null];

        // only one path
        yield [['/base/path'], '/base/path'];
        yield [['C:/base/path'], 'C:/base/path'];
        yield [['C:\\base\\path'], 'C:/base/path'];
        yield [['phar:///base/path'], 'phar:///base/path'];
        yield [['phar://C:/base/path'], 'phar://C:/base/path'];
    }

    /**
     * @dataProvider provideGetLongestCommonBasePathTests
     *
     * @param string[] $paths
     */
    public function testGetLongestCommonBasePath(array $paths, ?string $basePath)
    {
        $this->assertSame($basePath, Path::getLongestCommonBasePath(...$paths));
    }

    public static function provideIsBasePathTests(): \Generator
    {
        // same paths
        yield ['/base/path', '/base/path', true];
        yield ['C:/base/path', 'C:/base/path', true];
        yield ['C:\\base\\path', 'C:\\base\\path', true];
        yield ['C:/base/path', 'C:\\base\\path', true];
        yield ['phar:///base/path', 'phar:///base/path', true];
        yield ['phar://C:/base/path', 'phar://C:/base/path', true];

        // trailing slash
        yield ['/base/path/', '/base/path', true];
        yield ['C:/base/path/', 'C:/base/path', true];
        yield ['C:\\base\\path\\', 'C:\\base\\path', true];
        yield ['C:/base/path/', 'C:\\base\\path', true];
        yield ['phar:///base/path/', 'phar:///base/path', true];
        yield ['phar://C:/base/path/', 'phar://C:/base/path', true];

        yield ['/base/path', '/base/path/', true];
        yield ['C:/base/path', 'C:/base/path/', true];
        yield ['C:\\base\\path', 'C:\\base\\path\\', true];
        yield ['C:/base/path', 'C:\\base\\path\\', true];
        yield ['phar:///base/path', 'phar:///base/path/', true];
        yield ['phar://C:/base/path', 'phar://C:/base/path/', true];

        // first in second
        yield ['/base/path/sub', '/base/path', false];
        yield ['C:/base/path/sub', 'C:/base/path', false];
        yield ['C:\\base\\path\\sub', 'C:\\base\\path', false];
        yield ['C:/base/path/sub', 'C:\\base\\path', false];
        yield ['phar:///base/path/sub', 'phar:///base/path', false];
        yield ['phar://C:/base/path/sub', 'phar://C:/base/path', false];

        // second in first
        yield ['/base/path', '/base/path/sub', true];
        yield ['C:/base/path', 'C:/base/path/sub', true];
        yield ['C:\\base\\path', 'C:\\base\\path\\sub', true];
        yield ['C:/base/path', 'C:\\base\\path\\sub', true];
        yield ['phar:///base/path', 'phar:///base/path/sub', true];
        yield ['phar://C:/base/path', 'phar://C:/base/path/sub', true];

        // first is prefix
        yield ['/base/path/di', '/base/path/dir', false];
        yield ['C:/base/path/di', 'C:/base/path/dir', false];
        yield ['C:\\base\\path\\di', 'C:\\base\\path\\dir', false];
        yield ['C:/base/path/di', 'C:\\base\\path\\dir', false];
        yield ['phar:///base/path/di', 'phar:///base/path/dir', false];
        yield ['phar://C:/base/path/di', 'phar://C:/base/path/dir', false];

        // second is prefix
        yield ['/base/path/dir', '/base/path/di', false];
        yield ['C:/base/path/dir', 'C:/base/path/di', false];
        yield ['C:\\base\\path\\dir', 'C:\\base\\path\\di', false];
        yield ['C:/base/path/dir', 'C:\\base\\path\\di', false];
        yield ['phar:///base/path/dir', 'phar:///base/path/di', false];
        yield ['phar://C:/base/path/dir', 'phar://C:/base/path/di', false];

        // root
        yield ['/', '/second', true];
        yield ['C:/', 'C:/second', true];
        yield ['C:', 'C:/second', true];
        yield ['C:\\', 'C:\\second', true];
        yield ['C:/', 'C:\\second', true];
        yield ['phar:///', 'phar:///second', true];
        yield ['phar://C:/', 'phar://C:/second', true];

        // windows vs unix
        yield ['/base/path', 'C:/base/path', false];
        yield ['C:/base/path', '/base/path', false];
        yield ['/base/path', 'C:\\base\\path', false];
        yield ['/base/path', 'phar:///base/path', false];
        yield ['phar:///base/path', 'phar://C:/base/path', false];

        // different partitions
        yield ['C:/base/path', 'D:/base/path', false];
        yield ['C:/base/path', 'D:\\base\\path', false];
        yield ['C:\\base\\path', 'D:\\base\\path', false];
        yield ['C:/base/path', 'phar://C:/base/path', false];
        yield ['phar://C:/base/path', 'phar://D:/base/path', false];
    }

    /**
     * @dataProvider provideIsBasePathTests
     */
    public function testIsBasePath(string $path, string $ofPath, bool $result)
    {
        $this->assertSame($result, Path::isBasePath($path, $ofPath));
    }

    public static function provideJoinTests(): \Generator
    {
        yield [['', ''], ''];
        yield [['/path/to/test', ''], '/path/to/test'];
        yield [['/path/to//test', ''], '/path/to/test'];
        yield [['', '/path/to/test'], '/path/to/test'];
        yield [['', '/path/to//test'], '/path/to/test'];

        yield [['/path/to/test', 'subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test/', 'subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test', '/subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test/', '/subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test', './subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test/', './subdir'], '/path/to/test/subdir'];
        yield [['/path/to/test/', '../parentdir'], '/path/to/parentdir'];
        yield [['/path/to/test', '../parentdir'], '/path/to/parentdir'];
        yield [['path/to/test/', '/subdir'], 'path/to/test/subdir'];
        yield [['path/to/test', '/subdir'], 'path/to/test/subdir'];
        yield [['../path/to/test', '/subdir'], '../path/to/test/subdir'];
        yield [['path', '../../subdir'], '../subdir'];
        yield [['/path', '../../subdir'], '/subdir'];
        yield [['../path', '../../subdir'], '../../subdir'];

        yield [['/path/to/test', 'subdir', ''], '/path/to/test/subdir'];
        yield [['/path/to/test', '/subdir', ''], '/path/to/test/subdir'];
        yield [['/path/to/test/', 'subdir', ''], '/path/to/test/subdir'];
        yield [['/path/to/test/', '/subdir', ''], '/path/to/test/subdir'];

        yield [['/path', ''], '/path'];
        yield [['/path', 'to', '/test', ''], '/path/to/test'];
        yield [['/path', '', '/test', ''], '/path/test'];
        yield [['path', 'to', 'test', ''], 'path/to/test'];
        yield [[], ''];

        yield [['base/path', 'to/test'], 'base/path/to/test'];

        yield [['C:\\path\\to\\test', 'subdir'], 'C:/path/to/test/subdir'];
        yield [['C:\\path\\to\\test\\', 'subdir'], 'C:/path/to/test/subdir'];
        yield [['C:\\path\\to\\test', '/subdir'], 'C:/path/to/test/subdir'];
        yield [['C:\\path\\to\\test\\', '/subdir'], 'C:/path/to/test/subdir'];

        yield [['/', 'subdir'], '/subdir'];
        yield [['/', '/subdir'], '/subdir'];
        yield [['C:/', 'subdir'], 'C:/subdir'];
        yield [['C:/', '/subdir'], 'C:/subdir'];
        yield [['C:\\', 'subdir'], 'C:/subdir'];
        yield [['C:\\', '/subdir'], 'C:/subdir'];
        yield [['C:', 'subdir'], 'C:/subdir'];
        yield [['C:', '/subdir'], 'C:/subdir'];

        yield [['phar://', '/path/to/test'], 'phar:///path/to/test'];
        yield [['phar:///', '/path/to/test'], 'phar:///path/to/test'];
        yield [['phar:///path/to/test', 'subdir'], 'phar:///path/to/test/subdir'];
        yield [['phar:///path/to/test', 'subdir/'], 'phar:///path/to/test/subdir'];
        yield [['phar:///path/to/test', '/subdir'], 'phar:///path/to/test/subdir'];
        yield [['phar:///path/to/test/', 'subdir'], 'phar:///path/to/test/subdir'];
        yield [['phar:///path/to/test/', '/subdir'], 'phar:///path/to/test/subdir'];

        yield [['phar://', 'C:/path/to/test'], 'phar://C:/path/to/test'];
        yield [['phar://', 'C:\\path\\to\\test'], 'phar://C:/path/to/test'];
        yield [['phar://C:/path/to/test', 'subdir'], 'phar://C:/path/to/test/subdir'];
        yield [['phar://C:/path/to/test', 'subdir/'], 'phar://C:/path/to/test/subdir'];
        yield [['phar://C:/path/to/test', '/subdir'], 'phar://C:/path/to/test/subdir'];
        yield [['phar://C:/path/to/test/', 'subdir'], 'phar://C:/path/to/test/subdir'];
        yield [['phar://C:/path/to/test/', '/subdir'], 'phar://C:/path/to/test/subdir'];
        yield [['phar://C:', 'path/to/test'], 'phar://C:/path/to/test'];
        yield [['phar://C:', '/path/to/test'], 'phar://C:/path/to/test'];
        yield [['phar://C:/', 'path/to/test'], 'phar://C:/path/to/test'];
        yield [['phar://C:/', '/path/to/test'], 'phar://C:/path/to/test'];
    }

    /**
     * @dataProvider provideJoinTests
     */
    public function testJoin(array $paths, $result)
    {
        $this->assertSame($result, Path::join(...$paths));
    }

    public function testJoinVarArgs()
    {
        $this->assertSame('/path', Path::join('/path'));
        $this->assertSame('/path/to', Path::join('/path', 'to'));
        $this->assertSame('/path/to/test', Path::join('/path', 'to', '/test'));
        $this->assertSame('/path/to/test/subdir', Path::join('/path', 'to', '/test', 'subdir/'));
    }

    public function testGetHomeDirectoryFailsIfNotSupportedOperatingSystem()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Your environment or operating system isn\'t supported');

        putenv('HOME=');

        Path::getHomeDirectory();
    }

    public function testGetHomeDirectoryForUnix()
    {
        $this->assertEquals('/home/webmozart', Path::getHomeDirectory());
    }

    public function testGetHomeDirectoryForWindows()
    {
        putenv('HOME=');
        putenv('HOMEDRIVE=C:');
        putenv('HOMEPATH=/users/webmozart');

        $this->assertEquals('C:/users/webmozart', Path::getHomeDirectory());
    }

    public function testNormalize()
    {
        $this->assertSame('C:/Foo/Bar/test', Path::normalize('C:\\Foo\\Bar/test'));
    }
}
