<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\Helper\SlotsHelper;
use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Loader\LoaderInterface;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReference;
use Symfony\Component\Templating\TemplateReferenceInterface;

class PhpEngineTest extends TestCase
{
    protected $loader;

    protected function setUp(): void
    {
        $this->loader = new ProjectTemplateLoader();
    }

    protected function tearDown(): void
    {
        $this->loader = null;
    }

    public function testConstructor()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        self::assertEquals($this->loader, $engine->getLoader(), '__construct() takes a loader instance as its second first argument');
    }

    public function testOffsetGet()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $engine->set($helper = new \Symfony\Component\Templating\Tests\Fixtures\SimpleHelper('bar'), 'foo');
        self::assertEquals($helper, $engine['foo'], '->offsetGet() returns the value of a helper');

        try {
            $engine['bar'];
            self::fail('->offsetGet() throws an InvalidArgumentException if the helper is not defined');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->offsetGet() throws an InvalidArgumentException if the helper is not defined');
            self::assertEquals('The helper "bar" is not defined.', $e->getMessage(), '->offsetGet() throws an InvalidArgumentException if the helper is not defined');
        }
    }

    public function testGetSetHas()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $foo = new \Symfony\Component\Templating\Tests\Fixtures\SimpleHelper('foo');
        $engine->set($foo);
        self::assertEquals($foo, $engine->get('foo'), '->set() sets a helper');

        $engine[$foo] = 'bar';
        self::assertEquals($foo, $engine->get('bar'), '->set() takes an alias as a second argument');

        self::assertArrayHasKey('bar', $engine);

        try {
            $engine->get('foobar');
            self::fail('->get() throws an InvalidArgumentException if the helper is not defined');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->get() throws an InvalidArgumentException if the helper is not defined');
            self::assertEquals('The helper "foobar" is not defined.', $e->getMessage(), '->get() throws an InvalidArgumentException if the helper is not defined');
        }

        self::assertArrayHasKey('bar', $engine);
        self::assertTrue($engine->has('foo'), '->has() returns true if the helper exists');
        self::assertFalse($engine->has('foobar'), '->has() returns false if the helper does not exist');
    }

    public function testUnsetHelper()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $foo = new \Symfony\Component\Templating\Tests\Fixtures\SimpleHelper('foo');
        $engine->set($foo);

        self::expectException(\LogicException::class);

        unset($engine['foo']);
    }

    public function testExtendRender()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader, []);
        try {
            $engine->render('name');
            self::fail('->render() throws an InvalidArgumentException if the template does not exist');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->render() throws an InvalidArgumentException if the template does not exist');
            self::assertEquals('The template "name" does not exist.', $e->getMessage(), '->render() throws an InvalidArgumentException if the template does not exist');
        }

        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader, [new SlotsHelper()]);
        $engine->set(new \Symfony\Component\Templating\Tests\Fixtures\SimpleHelper('bar'));
        $this->loader->setTemplate('foo.php', '<?php $this->extend("layout.php"); echo $this[\'foo\'].$foo ?>');
        $this->loader->setTemplate('layout.php', '-<?php echo $this[\'slots\']->get("_content") ?>-');
        self::assertEquals('-barfoo-', $engine->render('foo.php', ['foo' => 'foo']), '->render() uses the decorator to decorate the template');

        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader, [new SlotsHelper()]);
        $engine->set(new \Symfony\Component\Templating\Tests\Fixtures\SimpleHelper('bar'));
        $this->loader->setTemplate('bar.php', 'bar');
        $this->loader->setTemplate('foo.php', '<?php $this->extend("layout.php"); echo $foo ?>');
        $this->loader->setTemplate('layout.php', '<?php echo $this->render("bar.php") ?>-<?php echo $this[\'slots\']->get("_content") ?>-');
        self::assertEquals('bar-foo-', $engine->render('foo.php', ['foo' => 'foo', 'bar' => 'bar']), '->render() supports render() calls in templates');
    }

    public function testRenderParameter()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $this->loader->setTemplate('foo.php', '<?php echo $template . $parameters ?>');
        self::assertEquals('foobar', $engine->render('foo.php', ['template' => 'foo', 'parameters' => 'bar']), '->render() extract variables');
    }

    /**
     * @dataProvider forbiddenParameterNames
     */
    public function testRenderForbiddenParameter($name)
    {
        self::expectException(\InvalidArgumentException::class);
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $this->loader->setTemplate('foo.php', 'bar');
        $engine->render('foo.php', [$name => 'foo']);
    }

    public function forbiddenParameterNames()
    {
        return [
            ['this'],
            ['view'],
        ];
    }

    public function testEscape()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        self::assertEquals('&lt;br /&gt;', $engine->escape('<br />'), '->escape() escapes strings');
        $foo = new \stdClass();
        self::assertEquals($foo, $engine->escape($foo), '->escape() does nothing on non strings');
    }

    public function testGetSetCharset()
    {
        $helper = new SlotsHelper();
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader, [$helper]);
        self::assertEquals('UTF-8', $engine->getCharset(), 'EngineInterface::getCharset() returns UTF-8 by default');
        self::assertEquals('UTF-8', $helper->getCharset(), 'HelperInterface::getCharset() returns UTF-8 by default');

        $engine->setCharset('ISO-8859-1');
        self::assertEquals('ISO-8859-1', $engine->getCharset(), 'EngineInterface::setCharset() changes the default charset to use');
        self::assertEquals('ISO-8859-1', $helper->getCharset(), 'EngineInterface::setCharset() changes the default charset of helper');
    }

    public function testGlobalVariables()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $engine->addGlobal('global_variable', 'lorem ipsum');

        self::assertEquals([
            'global_variable' => 'lorem ipsum',
        ], $engine->getGlobals());
    }

    public function testGlobalsGetPassedToTemplate()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);
        $engine->addGlobal('global', 'global variable');

        $this->loader->setTemplate('global.php', '<?php echo $global; ?>');

        self::assertEquals('global variable', $engine->render('global.php'));

        self::assertEquals('overwritten', $engine->render('global.php', ['global' => 'overwritten']));
    }

    public function testGetLoader()
    {
        $engine = new ProjectTemplateEngine(new TemplateNameParser(), $this->loader);

        self::assertSame($this->loader, $engine->getLoader());
    }
}

class ProjectTemplateEngine extends PhpEngine
{
    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }
}

class ProjectTemplateLoader extends Loader
{
    public $templates = [];

    public function setTemplate($name, $content)
    {
        $template = new TemplateReference($name, 'php');
        $this->templates[$template->getLogicalName()] = $content;
    }

    public function load(TemplateReferenceInterface $template)
    {
        if (isset($this->templates[$template->getLogicalName()])) {
            return new StringStorage($this->templates[$template->getLogicalName()]);
        }

        return false;
    }

    public function isFresh(TemplateReferenceInterface $template, int $time): bool
    {
        return false;
    }
}
