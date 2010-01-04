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

require_once __DIR__.'/../../../../../lib/SymfonyTests/Components/Templating/SimpleHelper.php';

use Symfony\Components\Templating\Engine;
use Symfony\Components\Templating\Renderer\Renderer;
use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Loader\FilesystemLoader;

$t = new LimeTest(3);

class ProjectTemplateRenderer extends Renderer
{
  public function getEngine()
  {
    return $this->engine;
  }

  public function evaluate(Storage $template, array $parameters = array())
  {
  }
}

$loader = new FilesystemLoader(array(__DIR__.'/fixtures/templates/%name%.%renderer%'));
$engine = new Engine($loader);
$engine->set('foo', 'bar');
$engine->getHelperSet()->set(new SimpleHelper('foo'), 'bar');

// ->setEngine()
$t->diag('->setEngine()');
$renderer = new ProjectTemplateRenderer();
$renderer->setEngine($engine);
$t->ok($renderer->getEngine() === $engine, '->setEngine() sets the engine instance tied to this renderer');

// __call()
$t->diag('__call()');
$renderer = new ProjectTemplateRenderer();
$renderer->setEngine($engine);
$t->is($renderer->get('foo'), 'bar', '__call() proxies to the embedded engine instance');

// __get()
$t->diag('__get()');
$renderer = new ProjectTemplateRenderer();
$renderer->setEngine($engine);
$t->is((string) $renderer->bar, 'foo', '__get() proxies to the embedded engine instance');
