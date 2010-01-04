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

use Symfony\Components\Templating\Loader\Loader;
use Symfony\Components\Templating\Loader\CacheLoader;
use Symfony\Components\Templating\Loader\CompilableLoaderInterface;

$t = new LimeTest(9);

class ProjectTemplateLoader extends CacheLoader
{
  public function getDir()
  {
    return $this->dir;
  }

  public function getLoader()
  {
    return $this->loader;
  }
}

class ProjectTemplateLoaderVar extends Loader
{
  public function getIndexTemplate()
  {
    return 'Hello World';
  }

  public function getSpecialTemplate()
  {
    return 'Hello {{ name }}';
  }

  public function load($template, $renderer = 'php')
  {
    if (method_exists($this, $method = 'get'.ucfirst($template).'Template'))
    {
      return $this->$method();
    }

    return false;
  }
}

class CompilableTemplateLoader extends ProjectTemplateLoaderVar implements CompilableLoaderInterface
{
  public function compile($template)
  {
    return preg_replace('/{{\s*([a-zA-Z0-9_]+)\s*}}/', '<?php echo $$1 ?>', $template);
  }
}

// __construct()
$t->diag('__construct()');
$loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(), sys_get_temp_dir());
$t->ok($loader->getLoader() === $varLoader, '__construct() takes a template loader as its first argument');
$t->is($loader->getDir(), sys_get_temp_dir(), '__construct() takes a directory where to store the cache as its second argument');

// ->load()
$t->diag('->load()');

$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.rand(111111, 999999);
mkdir($dir, 0777, true);

$loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(), $dir);
$loader->setDebugger($debugger = new ProjectTemplateDebugger());
$t->ok($loader->load('foo') === false, '->load() returns false if the embed loader is not able to load the template');
$loader->load('index');
$t->ok($debugger->hasMessage('Storing template'), '->load() logs a "Storing template" message if the template is found');
$loader->load('index');
$t->ok($debugger->hasMessage('Fetching template'), '->load() logs a "Storing template" message if the template is fetched from cache');

$t->diag('load() template compilation');
$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.rand(111111, 999999);
mkdir($dir, 0777, true);

$loader = new ProjectTemplateLoader(new CompilableTemplateLoader(), $dir);
$loader->setDebugger($debugger = new ProjectTemplateDebugger());
$template = $loader->load('special', 'comp');
$t->ok($debugger->hasMessage('Storing template'), '->load() logs a "Storing template" message if the template is found');
$t->is($template->getRenderer(), 'php', '->load() changes the renderer to php if the template is compilable');

$template = $loader->load('special', 'comp');
$t->ok($debugger->hasMessage('Fetching template'), '->load() logs a "Storing template" message if the template is fetched from cache');
$t->is($template->getRenderer(), 'php', '->load() changes the renderer to php if the template is compilable');
