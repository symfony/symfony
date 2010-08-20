<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\FileLoader;

class FileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\FileLoader::__construct
     */
    public function testConstructor()
    {
        $loader = new ProjectLoader(__DIR__);
        $this->assertEquals(array(__DIR__), $loader->paths, '__construct() takes a path as its second argument');

        $loader = new ProjectLoader(array(__DIR__, __DIR__));
        $this->assertEquals(array(__DIR__, __DIR__), $loader->paths, '__construct() takes an array of paths as its second argument');
    }

    /**
     * @covers Symfony\Component\Routing\Loader\FileLoader::GetAbsolutePath
     * @covers Symfony\Component\Routing\Loader\FileLoader::isAbsolutePath
     */
    public function testGetAbsolutePath()
    {
        $loader = new ProjectLoader(array(__DIR__.'/../Fixtures'));
        $this->assertEquals('/foo.xml', $loader->getAbsolutePath('/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:\\\\foo.xml', $loader->getAbsolutePath('c:\\\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:/foo.xml', $loader->getAbsolutePath('c:/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('\\server\\foo.xml', $loader->getAbsolutePath('\\server\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'FileLoaderTest.php', $loader->getAbsolutePath('FileLoaderTest.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

        $this->assertEquals(__DIR__.'/../Fixtures/foo.xml', $loader->getAbsolutePath('foo.xml', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');

        $this->assertEquals('foobar.xml', $loader->getAbsolutePath('foobar.xml', __DIR__), '->getAbsolutePath() returns the path unmodified if it is unable to find it in the given paths');
    }
}

class ProjectLoader extends FileLoader
{
    public $paths;

    public function load($resource)
    {
    }

    public function supports($resource)
    {
        return true;
    }

    public function getAbsolutePath($file, $currentPath = null)
    {
        return parent::getAbsolutePath($file, $currentPath);
    }
}
