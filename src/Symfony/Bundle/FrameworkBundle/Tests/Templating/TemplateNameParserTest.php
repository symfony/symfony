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
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Tests\Kernel;

class TemplateNameParserTest extends TestCase
{
    /**
     * @dataProvider getParseTests
     */
    public function testParse($name, $parameters)
    {
        $kernel = new Kernel();
        $kernel->boot();
        $parser = new TemplateNameParser($kernel);

        $this->assertEquals($parameters, $parser->parse($name));
    }

    public function getParseTests()
    {
        return array(
            array('FooBundle:Post:index.html.php', array('name' => 'index', 'bundle' => 'FooBundle', 'controller' => 'Post', 'engine' => 'php', 'format' => 'html')),
            array('FooBundle:Post:index.html.twig', array('name' => 'index', 'bundle' => 'FooBundle', 'controller' => 'Post', 'engine' => 'twig', 'format' => 'html')),
            array('FooBundle:Post:index.xml.php', array('name' => 'index', 'bundle' => 'FooBundle', 'controller' => 'Post', 'engine' => 'php', 'format' => 'xml')),
            array('SensioFooBundle:Post:index.html.php', array('name' => 'index', 'bundle' => 'SensioFooBundle', 'controller' => 'Post', 'engine' => 'php', 'format' => 'html')),
            array('SensioCmsFooBundle:Post:index.html.php', array('name' => 'index', 'bundle' => 'SensioCmsFooBundle', 'controller' => 'Post', 'engine' => 'php', 'format' => 'html')),
            array(':Post:index.html.php',array('name' => 'index', 'bundle' => '', 'controller' => 'Post', 'engine' => 'php', 'format' => 'html')),
            array('::index.html.php', array('name' => 'index', 'bundle' => '', 'controller' => '', 'engine' => 'php', 'format' => 'html')),
        );
    }

    /**
     * @dataProvider      getParseInvalidTests
     * @expectedException \InvalidArgumentException
     */
    public function testParseInvalid($name)
    {
        $kernel = new Kernel();
        $kernel->boot();
        $parser = new TemplateNameParser($kernel);

        $parser->parse($name);
    }

    public function getParseInvalidTests()
    {
        return array(
            array('BarBundle:Post:index.html.php'),
            array('FooBundle:Post:index'),
            array('FooBundle:Post'),
            array('FooBundle:Post:foo:bar'),
            array('FooBundle:Post:index.foo.bar.foobar'),
        );
    }
}
