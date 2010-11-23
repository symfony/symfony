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

// simulate the rendering of another controller
function foo($engine)
{
    return $engine->render('FooBundle:Foo:tpl1.php', array('foo' => 'foo <br />'));
}

class EngineTest extends TestCase
{
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
