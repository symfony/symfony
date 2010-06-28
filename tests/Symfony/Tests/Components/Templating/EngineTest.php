<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating;

require_once __DIR__.'/Fixtures/SimpleHelper.php';

use Symfony\Components\Templating\Engine;
use Symfony\Components\Templating\Loader\Loader;
use Symfony\Components\Templating\Renderer\Renderer;
use Symfony\Components\Templating\Renderer\PhpRenderer;
use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\StringStorage;
use Symfony\Components\Templating\Helper\SlotsHelper;

class EngineTest extends \PHPUnit_Framework_TestCase
{
    static protected $loader, $renderer;

    static public function setUpBeforeClass()
    {
        self::$loader = new ProjectTemplateLoader();
        self::$renderer = new ProjectTemplateRenderer();
    }

    public function testConstructor()
    {
        $engine = new ProjectTemplateEngine(self::$loader);
        $this->assertEquals(self::$loader, $engine->getLoader(), '__construct() takes a loader instance as its second first argument');
        $this->assertEquals(array('php'), array_keys($engine->getRenderers()), '__construct() automatically registers a PHP renderer if none is given');

        $engine = new ProjectTemplateEngine(self::$loader, array('foo' => self::$renderer));
        $this->assertEquals(array('foo', 'php'), array_keys($engine->getRenderers()), '__construct() takes an array of renderers as its third argument');
        $this->assertTrue(self::$renderer->getEngine() === $engine, '__construct() registers itself on all renderers');

        $engine = new ProjectTemplateEngine(self::$loader, array('php' => self::$renderer));
        $this->assertTrue($engine->getRenderers() === array('php' => self::$renderer), '__construct() can overridde the default PHP renderer');
    }

    public function testMagicGet()
    {
        $engine = new ProjectTemplateEngine(self::$loader);
        $engine->set($helper = new \SimpleHelper('bar'), 'foo');
        $this->assertEquals($helper, $engine->foo, '->__get() returns the value of a helper');

        try {
            $engine->bar;
            $this->fail('->__get() throws an InvalidArgumentException if the helper is not defined');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->__get() throws an InvalidArgumentException if the helper is not defined');
            $this->assertEquals('The helper "bar" is not defined.', $e->getMessage(), '->__get() throws an InvalidArgumentException if the helper is not defined');
        }
    }

    public function testGetSetHas()
    {
        $engine = new ProjectTemplateEngine(self::$loader);
        $foo = new \SimpleHelper('foo');
        $engine->set($foo);
        $this->assertEquals($foo, $engine->get('foo'), '->set() sets a helper');

        $engine->set($foo, 'bar');
        $this->assertEquals($foo, $engine->get('bar'), '->set() takes an alias as a second argument');

        try {
            $engine->get('foobar');
            $this->fail('->get() throws an InvalidArgumentException if the helper is not defined');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->get() throws an InvalidArgumentException if the helper is not defined');
            $this->assertEquals('The helper "foobar" is not defined.', $e->getMessage(), '->get() throws an InvalidArgumentException if the helper is not defined');
        }

        $this->assertTrue($engine->has('foo'), '->has() returns true if the helper exists');
        $this->assertFalse($engine->has('foobar'), '->has() returns false if the helper does not exist');
    }

    public function testExtendRender()
    {
        $engine = new ProjectTemplateEngine(self::$loader, array(), array(new SlotsHelper()));
        try {
            $engine->render('name');
            $this->fail('->render() throws an InvalidArgumentException if the template does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->render() throws an InvalidArgumentException if the template does not exist');
            $this->assertEquals('The template "name" does not exist (renderer: php).', $e->getMessage(), '->render() throws an InvalidArgumentException if the template does not exist');
        }

        try {
            self::$loader->setTemplate('name.foo', 'foo');
            $engine->render('foo:name');
            $this->fail('->render() throws an InvalidArgumentException if no renderer is registered for the given renderer');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->render() throws an InvalidArgumentException if no renderer is registered for the given renderer');
            $this->assertEquals('The template "foo" does not exist (renderer: name).', $e->getMessage(), '->render() throws an InvalidArgumentException if no renderer is registered for the given renderer');
        }

        $engine = new ProjectTemplateEngine(self::$loader, array(), array(new SlotsHelper()));
        $engine->set(new \SimpleHelper('bar'));
        self::$loader->setTemplate('foo.php', '<?php $view->extend("layout"); echo $view->foo.$foo ?>');
        self::$loader->setTemplate('layout.php', '-<?php echo $view->slots->get("_content") ?>-');
        $this->assertEquals('-barfoo-', $engine->render('foo', array('foo' => 'foo')), '->render() uses the decorator to decorate the template');

        $engine = new ProjectTemplateEngine(self::$loader, array(), array(new SlotsHelper()));
        $engine->set(new \SimpleHelper('bar'));
        self::$loader->setTemplate('bar.php', 'bar');
        self::$loader->setTemplate('foo.php', '<?php $view->extend("layout"); echo $foo ?>');
        self::$loader->setTemplate('layout.php', '<?php echo $view->render("bar") ?>-<?php echo $view->slots->get("_content") ?>-');
        $this->assertEquals('bar-foo-', $engine->render('foo', array('foo' => 'foo', 'bar' => 'bar')), '->render() supports render() calls in templates');
    }

    public function testEscape()
    {
        $engine = new ProjectTemplateEngine(self::$loader);
        $this->assertEquals('&lt;br /&gt;', $engine->escape('<br />'), '->escape() escapes strings');
        $foo = new \stdClass();
        $this->assertEquals($foo, $engine->escape($foo), '->escape() does nothing on non strings');
    }

    public function testGetSetCharset()
    {
        $engine = new ProjectTemplateEngine(self::$loader);
        $this->assertEquals('UTF-8', $engine->getCharset(), '->getCharset() returns UTF-8 by default');
        $engine->setCharset('ISO-8859-1');
        $this->assertEquals('ISO-8859-1', $engine->getCharset(), '->setCharset() changes the default charset to use');
    }
}

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

    public function load($template, array $options = array())
    {
        if (isset($this->templates[$template.'.'.$options['renderer']])) {
            return new StringStorage($this->templates[$template.'.'.$options['renderer']]);
        }

        return false;
    }

    public function isFresh($template, array $options = array(), $time)
    {
        return false;
    }
}

class FooTemplateRenderer extends Renderer
{
    public function evaluate(Storage $template, array $parameters = array())
    {
        return 'foo';
    }
}
