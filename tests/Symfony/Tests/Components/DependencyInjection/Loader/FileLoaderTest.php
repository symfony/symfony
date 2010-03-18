<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Loader\FileLoader;

class XmlDumperTest extends \PHPUnit_Framework_TestCase
{
  public function testConstructor()
  {
    $loader = new ProjectLoader(__DIR__);
    $this->assertEquals($loader->paths, array(__DIR__), '__construct() takes a path as its second argument');

    $loader = new ProjectLoader(array(__DIR__, __DIR__));
    $this->assertEquals($loader->paths, array(__DIR__, __DIR__), '__construct() takes an array of paths as its second argument');
  }

  public function testGetAbsolutePath()
  {
    $loader = new ProjectLoader(array(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/containers'));
    $this->assertEquals($loader->getAbsolutePath('/foo.xml'), '/foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
    $this->assertEquals($loader->getAbsolutePath('c:\\\\foo.xml'), 'c:\\\\foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
    $this->assertEquals($loader->getAbsolutePath('c:/foo.xml'), 'c:/foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
    $this->assertEquals($loader->getAbsolutePath('\\server\\foo.xml'), '\\server\\foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');

    $this->assertEquals($loader->getAbsolutePath('FileLoaderTest.php', __DIR__), __DIR__.'/FileLoaderTest.php', '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

    $this->assertEquals($loader->getAbsolutePath('container10.php', __DIR__), __DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/containers/container10.php', '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');

    $this->assertEquals($loader->getAbsolutePath('foo.xml', __DIR__), 'foo.xml', '->getAbsolutePath() returns the path unmodified if it is unable to find it in the given paths');
  }
}

class ProjectLoader extends FileLoader
{
  public $paths;

  public function load($resource)
  {
  }

  public function getAbsolutePath($file, $currentPath = null)
  {
    return parent::getAbsolutePath($file, $currentPath);
  }
}
