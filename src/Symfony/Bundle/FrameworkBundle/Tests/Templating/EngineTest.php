<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\Engine;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Renderer\PhpRenderer;
use Symfony\Component\OutputEscaper\Escaper;

// simulate the rendering of another controller
function foo($engine)
{
    return $engine->render('FooBundle:Foo:tpl1.php', array('foo' => 'foo <br />'));
}

class EngineTest extends TestCase
{
    public function testRenderEscaping()
    {
        $templates = array(
            'tpl1'  => '<?php echo $foo ?>',
            'tpl2'  => '<?php echo $foo.$view->render("FooBundle:Foo:tpl1.php", array("foo" => $foo)) ?>',
            'tpl3'  => '<?php echo $foo.$view->render("FooBundle:Foo:tpl1.php", array("foo" => "foo <br />")) ?>',
            'tpl4'  => '<?php echo $foo.Symfony\Bundle\FrameworkBundle\Tests\Templating\foo($view) ?>',
        );

        $loader = $this->getMock('Symfony\Component\Templating\Loader\LoaderInterface');
        $loader->expects($this->exactly(4))
            ->method('load')
            ->with($this->anything(), $this->anything())
            ->will($this->onConsecutiveCalls(
                new StringStorage($templates['tpl1']),
                new StringStorage($templates['tpl2']),
                new StringStorage($templates['tpl3']),
                new StringStorage($templates['tpl4'])
            ))
        ;

        $engine = new Engine($this->getContainerMock(), $loader, array('php' => new PhpRenderer()), 'htmlspecialchars');

        $this->assertEquals('foo &lt;br /&gt;', $engine->render('FooBundle:Foo:tpl1.php', array('foo' => 'foo <br />')));
        $this->assertEquals('foo &lt;br /&gt;', $engine->render('FooBundle:Foo:tpl1.php', array('foo' => 'foo <br />')));

        $this->assertEquals('foo &lt;br /&gt;foo &lt;br /&gt;', $engine->render('FooBundle:Foo:tpl2.php', array('foo' => 'foo <br />')));
        $this->assertEquals('foo &lt;br /&gt;foo &lt;br /&gt;', $engine->render('FooBundle:Foo:tpl3.php', array('foo' => 'foo <br />')));
        $this->assertEquals('foo &lt;br /&gt;foo &lt;br /&gt;', $engine->render('FooBundle:Foo:tpl4.php', array('foo' => 'foo <br />')));
    }

    /**
     * @dataProvider getSplitTemplateNameTests
     */
    public function testSplitTemplateName($name, $parameters)
    {
        $engine = new Engine($this->getContainerMock(), $this->getLoaderMock());

        $this->assertEquals($parameters, $engine->splitTemplateName($name));
    }

    public function getSplitTemplateNameTests()
    {
        return array(
            array('BlogBundle:Post:index.php', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => ''))),
            array('BlogBundle:Post:index.twig', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'twig', 'format' => ''))),
            array('BlogBundle:Post:index.xml.php', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => '.xml'))),
        );
    }

    /**
     * @dataProvider      getSplitTemplateNameInvalidTests
     * @expectedException \InvalidArgumentException
     */
    public function testSplitTemplateNameInvalid($name)
    {
        $engine = new Engine($this->getContainerMock(), $this->getLoaderMock());

        $engine->splitTemplateName($name);
    }

    public function getSplitTemplateNameInvalidTests()
    {
        return array(
            array('BlogBundle:Post:index'),
            array('BlogBundle:Post'),
            array('BlogBundle:Post:foo:bar'),
            array('BlogBundle:Post:index.foo.bar.foobar'),
        );
    }

    protected function getContainerMock()
    {
        $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
        $request
            ->expects($this->any())
            ->method('getRequestFormat')
            ->will($this->returnValue('html'))
        ;

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container
            ->expects($this->exactly(2))
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array()))
        ;
        $container
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValue($request))
        ;

        return $container;
    }

    protected function getLoaderMock()
    {
        return $this->getMock('Symfony\Component\Templating\Loader\LoaderInterface');
    }
}
