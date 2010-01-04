<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Loader\FileLoader;

$t = new LimeTest(9);

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

// __construct()
$t->diag('__construct()');
$loader = new ProjectLoader(__DIR__);
$t->is($loader->paths, array(__DIR__), '__construct() takes a path as its second argument');

$loader = new ProjectLoader(array(__DIR__, __DIR__));
$t->is($loader->paths, array(__DIR__, __DIR__), '__construct() takes an array of paths as its second argument');

// ->getAbsolutePath()
$t->diag('->getAbsolutePath()');
$loader = new ProjectLoader(array(__DIR__.'/../../../../../bin'));
$t->is($loader->getAbsolutePath('/foo.xml'), '/foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
$t->is($loader->getAbsolutePath('c:\\\\foo.xml'), 'c:\\\\foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
$t->is($loader->getAbsolutePath('c:/foo.xml'), 'c:/foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');
$t->is($loader->getAbsolutePath('\\server\\foo.xml'), '\\server\\foo.xml', '->getAbsolutePath() return the path unmodified if it is already an absolute path');

$t->is($loader->getAbsolutePath('FileLoaderTest.php', __DIR__), __DIR__.'/FileLoaderTest.php', '->getAbsolutePath() returns an absolute filename if the file exists in the current path');

$t->is($loader->getAbsolutePath('prove.php', __DIR__), __DIR__.'/../../../../../bin/prove.php', '->getAbsolutePath() returns an absolute filename if the file exists in one of the paths given in the constructor');

$t->is($loader->getAbsolutePath('foo.xml', __DIR__), 'foo.xml', '->getAbsolutePath() returns the path unmodified if it is unable to find it in the given paths');
