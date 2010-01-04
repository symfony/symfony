<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

require_once __DIR__.'/../../../../../lib/SymfonyTests/Components/Templating/ProjectTemplateDebugger.php';

use Symfony\Components\Templating\Loader\FilesystemLoader;
use Symfony\Components\Templating\Storage\FileStorage;

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/Templating/');

$t = new LimeTest(15);

class ProjectTemplateLoader extends FilesystemLoader
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

// ->isAbsolutePath()
$t->diag('->isAbsolutePath()');
$t->ok(ProjectTemplateLoader::isAbsolutePath('/foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
$t->ok(ProjectTemplateLoader::isAbsolutePath('c:\\\\foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
$t->ok(ProjectTemplateLoader::isAbsolutePath('c:/foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');
$t->ok(ProjectTemplateLoader::isAbsolutePath('\\server\\foo.xml'), '->isAbsolutePath() returns true if the path is an absolute path');

// __construct()
$t->diag('__construct()');
$pathPattern = $fixturesPath.'/templates/%name%.%renderer%';
$path = $fixturesPath.'/templates';
$loader = new ProjectTemplateLoader($pathPattern);
$t->is($loader->getTemplatePathPatterns(), array($pathPattern), '__construct() takes a path as its second argument');
$loader = new ProjectTemplateLoader(array($pathPattern));
$t->is($loader->getTemplatePathPatterns(), array($pathPattern), '__construct() takes an array of paths as its second argument');

// ->load()
$t->diag('->load()');
$loader = new ProjectTemplateLoader($pathPattern);
$storage = $loader->load($path.'/foo.php');
$t->ok($storage instanceof FileStorage, '->load() returns a FileStorage if you pass an absolute path');
$t->is((string) $storage, $path.'/foo.php', '->load() returns a FileStorage pointing to the passed absolute path');

$t->ok($loader->load('bar') === false, '->load() returns false if the template is not found');

$storage = $loader->load('foo');
$t->ok($storage instanceof FileStorage, '->load() returns a FileStorage if you pass a relative template that exists');
$t->is((string) $storage, $path.'/foo.php', '->load() returns a FileStorage pointing to the absolute path of the template');

$loader = new ProjectTemplateLoader($pathPattern);
$loader->setDebugger($debugger = new ProjectTemplateDebugger());
$t->ok($loader->load('foo', 'xml') === false, '->load() returns false if the template does not exists for the given renderer');
$t->ok($debugger->hasMessage('Failed loading template'), '->load() logs a "Failed loading template" message if the template is not found');

$loader = new ProjectTemplateLoader(array($fixturesPath.'/null/%name%', $pathPattern));
$loader->setDebugger($debugger = new ProjectTemplateDebugger());
$loader->load('foo');
$t->ok($debugger->hasMessage('Failed loading template'), '->load() logs a "Failed loading template" message if the template is not found');
$t->ok($debugger->hasMessage('Loaded template file'), '->load() logs a "Loaded template file" message if the template is found');
