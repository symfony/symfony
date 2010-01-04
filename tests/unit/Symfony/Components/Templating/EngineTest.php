<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

require_once __DIR__.'/../../../../lib/SymfonyTests/Components/Templating/SimpleHelper.php';

use Symfony\Components\Templating\Engine;
use Symfony\Components\Templating\Loader\Loader;
use Symfony\Components\Templating\Loader\CompilableLoaderInterface;
use Symfony\Components\Templating\Helper\HelperSet;
use Symfony\Components\Templating\Renderer\Renderer;
use Symfony\Components\Templating\Renderer\PhpRenderer;
use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\StringStorage;

$t = new LimeTest(33);

class ProjectTemplateEngine extends Engine
{
  public function getLoader()
  {
    return $this->loader;
  }

  public function getRenderers()
  {
    return $this->renderers;
  }
}

class ProjectTemplateRenderer extends PhpRenderer
{
  public function getEngine()
  {
    return $this->engine;
  }
}

class ProjectTemplateLoader extends Loader
{
  public $templates = array();

  public function setTemplate($name, $template)
  {
    $this->templates[$name] = $template;
  }

  public function load($template, $renderer = 'php')
  {
    if (isset($this->templates[$template.'.'.$renderer]))
    {
      return new StringStorage($this->templates[$template.'.'.$renderer]);
    }

    return false;
  }
}

$loader = new ProjectTemplateLoader();
$renderer = new ProjectTemplateRenderer();

// __construct()
$t->diag('__construct()');
$engine = new ProjectTemplateEngine($loader);
$t->is($engine->getLoader(), $loader, '__construct() takes a loader instance as its second first argument');
$t->is(array_keys($engine->getRenderers()), array('php'), '__construct() automatically registers a PHP renderer if none is given');
$t->ok($engine->getHelperSet() instanceof HelperSet, '__construct() automatically creates a helper set if none is given');

$engine = new ProjectTemplateEngine($loader, array('foo' => $renderer));
$t->is(array_keys($engine->getRenderers()), array('foo', 'php'), '__construct() takes an array of renderers as its third argument');
$t->ok($renderer->getEngine() === $engine, '__construct() registers itself on all renderers');

$engine = new ProjectTemplateEngine($loader, array('php' => $renderer));
$t->ok($engine->getRenderers() === array('php' => $renderer), '__construct() can overridde the default PHP renderer');

$engine = new ProjectTemplateEngine($loader, array(), $helperSet = new HelperSet());
$t->ok($engine->getHelperSet() === $helperSet, '__construct() takes a helper set as its third argument');

// ->getHelperSet() ->setHelperSet()
$t->diag('->getHelperSet() ->setHelperSet()');
$engine = new ProjectTemplateEngine($loader);
$engine->setHelperSet(new HelperSet(array('foo' => $helper = new SimpleHelper('bar'))));
$t->is((string) $engine->getHelperSet()->get('foo'), 'bar', '->setHelperSet() sets a helper set');

// __get()
$t->diag('__get()');
$t->is($engine->foo, $helper, '->__get() returns the value of a helper');

try
{
  $engine->bar;
  $t->fail('->__get() throws an InvalidArgumentException if the helper is not defined');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->__get() throws an InvalidArgumentException if the helper is not defined');
}

// ->get() ->set() ->has()
$t->diag('->get() ->set() ->has()');
$engine = new ProjectTemplateEngine($loader);
$engine->set('foo', 'bar');
$t->is($engine->get('foo'), 'bar', '->set() sets a slot value');
$t->is($engine->get('bar', 'bar'), 'bar', '->get() takes a default value to return if the slot does not exist');

$t->ok($engine->has('foo'), '->has() returns true if the slot exists');
$t->ok(!$engine->has('bar'), '->has() returns false if the slot does not exist');

// ->output()
$t->diag('->output()');
ob_start();
$ret = $engine->output('foo');
$output = ob_get_clean();
$t->is($output, 'bar', '->output() outputs the content of a slot');
$t->is($ret, true, '->output() returns true if the slot exists');

ob_start();
$ret = $engine->output('bar', 'bar');
$output = ob_get_clean();
$t->is($output, 'bar', '->output() takes a default value to return if the slot does not exist');
$t->is($ret, true, '->output() returns true if the slot does not exist but a default value is provided');

ob_start();
$ret = $engine->output('bar');
$output = ob_get_clean();
$t->is($output, '', '->output() outputs nothing if the slot does not exist');
$t->is($ret, false, '->output() returns false if the slot does not exist');

// ->start() ->stop()
$t->diag('->start() ->stop()');
$engine->start('bar');
echo 'foo';
$engine->stop();
$t->is($engine->get('bar'), 'foo', '->start() starts a slot');
$t->ok($engine->has('bar'), '->starts() starts a slot');

$engine->start('bar');
try
{
  $engine->start('bar');
  $engine->stop();
  $t->fail('->start() throws an InvalidArgumentException if a slot with the same name is already started');
}
catch (InvalidArgumentException $e)
{
  $engine->stop();
  $t->pass('->start() throws an InvalidArgumentException if a slot with the same name is already started');
}

try
{
  $engine->stop();
  $t->fail('->stop() throws an LogicException if no slot is started');
}
catch (LogicException $e)
{
  $t->pass('->stop() throws an LogicException if no slot is started');
}

// ->extend() ->render()
$t->diag('->extend() ->render()');
$engine = new ProjectTemplateEngine($loader);
try
{
  $engine->render('name');
  $t->fail('->render() throws an InvalidArgumentException if the template does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->render() throws an InvalidArgumentException if the template does not exist');
}

try
{
  $loader->setTemplate('name.foo', 'foo');
  $engine->render('foo:name');
  $t->fail('->render() throws an InvalidArgumentException if no renderer is registered for the given renderer');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->render() throws an InvalidArgumentException if no renderer is registered for the given renderer');
}

$engine->getHelperSet()->set(new SimpleHelper('bar'));
$loader->setTemplate('foo.php', '<?php $this->extend("layout"); echo $this->foo.$foo ?>');
$loader->setTemplate('layout.php', '-<?php echo $this->get("content") ?>-');
$t->is($engine->render('foo', array('foo' => 'foo')), '-barfoo-', '->render() uses the decorator to decorate the template');

$loader->setTemplate('bar.php', 'bar');
$loader->setTemplate('foo.php', '<?php $this->extend("layout"); echo $foo ?>');
$loader->setTemplate('layout.php', '<?php echo $this->render("bar") ?>-<?php echo $this->get("content") ?>-');
$t->is($engine->render('foo', array('foo' => 'foo', 'bar' => 'bar')), 'bar-foo-', '->render() supports render() calls in templates');

class CompilableTemplateLoader extends Loader implements CompilableLoaderInterface
{
  public function load($template, $renderer = 'php')
  {
    return new StringStorage($template, 'foo');
  }

  public function compile($template)
  {
    return 'COMPILED';
  }
}

class FooTemplateRenderer extends Renderer
{
  public function evaluate(Storage $template, array $parameters = array())
  {
    return 'foo';
  }
}

$t->diag('compilable templates');
$engine = new ProjectTemplateEngine(new CompilableTemplateLoader(), array('foo' => new FooTemplateRenderer()));
$t->is($engine->render('index'), 'foo', '->load() takes into account the renderer embedded in the Storage instance if not null');

// ->escape()
$t->diag('->escape()');
$engine = new ProjectTemplateEngine($loader);
$t->is($engine->escape('<br />'), '&lt;br /&gt;', '->escape() escapes strings');
$t->is($engine->escape($foo = new stdClass()), $foo, '->escape() does nothing on non strings');

// ->getCharset() ->setCharset()
$t->diag('->getCharset() ->setCharset()');
$engine = new ProjectTemplateEngine($loader);
$t->is($engine->getCharset(), 'UTF-8', '->getCharset() returns UTF-8 by default');
$engine->setCharset('ISO-8859-1');
$t->is($engine->getCharset(), 'ISO-8859-1', '->setCharset() changes the default charset to use');
