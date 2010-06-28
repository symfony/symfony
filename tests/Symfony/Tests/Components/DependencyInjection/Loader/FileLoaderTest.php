<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\Loader\FileLoader;

class FileLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Components\DependencyInjection\Loader\FileLoader::__construct
     */
    public function testConstructor()
    {
        $loader = new ProjectLoader(__DIR__);
        $this->assertEquals(array(__DIR__), $loader->paths, '__construct() takes a path as its second argument');

        $loader = new ProjectLoader(array(__DIR__, __DIR__));
        $this->assertEquals(array(__DIR__, __DIR__), $loader->paths, '__construct() takes an array of paths as its second argument');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\FileLoader::GetAbsolutePath
     */
    public function testGetAbsolutePath()
    {
        $loader = new ProjectLoader(array(__DIR__.'/../Fixtures/containers'));
        $this->assertEquals('/foo.xml', $loader->getAbsolutePath('/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:\\\\foo.xml', $loader->getAbsolutePath('c:\\\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('c:/foo.xml', $loader->getAbsolutePath('c:/foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');
        $this->assertEquals('\\server\\foo.xml', $loader->getAbsolutePath('\\server\\foo.xml'), '->getAbsolutePath() return the path unmodified if it is already an absolute path');

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'FileLoaderTest.php', $loader->getAbsolutePath('FileLoaderTest.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

        $this->assertEquals(__DIR__.'/../Fixtures/containers'.DIRECTORY_SEPARATOR.'container10.php', $loader->getAbsolutePath('container10.php', __DIR__), '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');

        $this->assertEquals('foo.xml', $loader->getAbsolutePath('foo.xml', __DIR__), '->getAbsolutePath() returns the path unmodified if it is unable to find it in the given paths');
    }
}

class ProjectLoader extends FileLoader
{
    public $paths;

    public function load($resource, $main = true, BuilderConfiguration $configuration = null)
    {
    }

    public function getAbsolutePath($file, $currentPath = null)
    {
        return parent::getAbsolutePath($file, $currentPath);
    }
}
