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
        $converter = new TemplateNameParser($kernel);

        $this->assertEquals($parameters, $converter->parse($name));
    }

    public function getParseTests()
    {
        return array(
            array('FooBundle:Post:index.php.html', array('index', array('bundle' => 'FooBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'html'))),
            array('FooBundle:Post:index.twig.html', array('index', array('bundle' => 'FooBundle', 'controller' => 'Post', 'renderer' => 'twig', 'format' => 'html'))),
            array('FooBundle:Post:index.php.xml', array('index', array('bundle' => 'FooBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'xml'))),
            array('SensioFooBundle:Post:index.php.html', array('index', array('bundle' => 'Sensio/FooBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'html'))),
            array('SensioCmsFooBundle:Post:index.php.html', array('index', array('bundle' => 'Sensio/Cms/FooBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'html'))),
            array(':Post:index.php.html', array('index', array('bundle' => '', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'html'))),
            array('::index.php.html', array('index', array('bundle' => '', 'controller' => '', 'renderer' => 'php', 'format' => 'html'))),
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
        $converter = new TemplateNameParser($kernel);

        $converter->parse($name);
    }

    public function getParseInvalidTests()
    {
        return array(
            array('BarBundle:Post:index.php.html'),
            array('FooBundle:Post:index'),
            array('FooBundle:Post'),
            array('FooBundle:Post:foo:bar'),
            array('FooBundle:Post:index.foo.bar.foobar'),
        );
    }
}
