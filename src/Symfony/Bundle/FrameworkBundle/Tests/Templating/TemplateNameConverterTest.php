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
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameConverter;

class TemplateNameConverterTest extends TestCase
{
    /**
     * @dataProvider getFromShortNotationTests
     */
    public function testFromShortNotation($name, $parameters)
    {
        $converter = new TemplateNameConverter($this->getContainerMock(), $this->getLoaderMock(), array());

        $this->assertEquals($parameters, $converter->fromShortNotation($name));
    }

    public function getFromShortNotationTests()
    {
        return array(
            array('BlogBundle:Post:index.php', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => ''))),
            array('BlogBundle:Post:index.twig', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'twig', 'format' => ''))),
            array('BlogBundle:Post:index.xml.php', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => '.xml'))),
        );
    }

    /**
     * @dataProvider      getFromShortNotationInvalidTests
     * @expectedException \InvalidArgumentException
     */
    public function testFromShortNotationInvalid($name)
    {
        $converter = new TemplateNameConverter($this->getContainerMock(), $this->getLoaderMock(), array());

        $converter->fromShortNotation($name);
    }

    public function getFromShortNotationInvalidTests()
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
