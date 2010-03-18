<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Loader;

require_once __DIR__.'/../../../bootstrap.php';

require_once __DIR__.'/../../../../../lib/SymfonyTests/Components/Templating/ProjectTemplateDebugger.php';

use Symfony\Components\Templating\Loader\FilesystemLoader;
use Symfony\Components\Templating\Storage\FileStorage;

class FilesystemLoaderTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/Templating/');
  }

  public function testConstructor()
  {
    $pathPattern = self::$fixturesPath.'/templates/%name%.%renderer%';
    $path = self::$fixturesPath.'/templates';
    $loader = new ProjectTemplateLoader2($pathPattern);
    $this->assertEquals($loader->getTemplatePathPatterns(), array($pathPattern), '__construct() takes a path as its second argument');
    $loader = new ProjectTemplateLoader2(array($pathPattern));
    $this->assertEquals($loader->getTemplatePathPatterns(), array($pathPattern), '__construct() takes an array of paths as its second argument');
  }

  public function testIsAbsolutePath()
  {
    $this->assertTrue(ProjectTemplateLoader2::isAbsolutePath('/foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
    $this->assertTrue(ProjectTemplateLoader2::isAbsolutePath('c:\\\\foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
    $this->assertTrue(ProjectTemplateLoader2::isAbsolutePath('c:/foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
    $this->assertTrue(ProjectTemplateLoader2::isAbsolutePath('\\server\\foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
  }

  public function testLoad()
  {
    $pathPattern = self::$fixturesPath.'/templates/%name%.%renderer%';
    $path = self::$fixturesPath.'/templates';
    $loader = new ProjectTemplateLoader2($pathPattern);
    $storage = $loader->load($path.'/foo.php');
    $this->assertTrue($storage instanceof FileStorage, '->load() returns a FileStorage if you pass an absolute path');
    $this->assertEquals((string) $storage, $path.'/foo.php', '->load() returns a FileStorage pointing to the passed absolute path');

    $this->assertTrue($loader->load('bar') === false, '->load() returns false if the template is not found');

    $storage = $loader->load('foo');
    $this->assertTrue($storage instanceof FileStorage, '->load() returns a FileStorage if you pass a relative template that exists');
    $this->assertEquals((string) $storage, $path.'/foo.php', '->load() returns a FileStorage pointing to the absolute path of the template');

    $loader = new ProjectTemplateLoader2($pathPattern);
    $loader->setDebugger($debugger = new \ProjectTemplateDebugger());
    $this->assertTrue($loader->load('foo', array('renderer' => 'xml')) === false, '->load() returns false if the template does not exists for the given renderer');
    $this->assertTrue($debugger->hasMessage('Failed loading template'), '->load() logs a "Failed loading template" message if the template is not found');

    $loader = new ProjectTemplateLoader2(array(self::$fixturesPath.'/null/%name%', $pathPattern));
    $loader->setDebugger($debugger = new \ProjectTemplateDebugger());
    $loader->load('foo');
    $this->assertTrue($debugger->hasMessage('Loaded template file'), '->load() logs a "Loaded template file" message if the template is found');
  }
}

class ProjectTemplateLoader2 extends FilesystemLoader
{
  public function getTemplatePathPatterns()
  {
    return $this->templatePathPatterns;
  }

  static public function isAbsolutePath($path)
  {
    return parent::isAbsolutePath($path);
  }
}
