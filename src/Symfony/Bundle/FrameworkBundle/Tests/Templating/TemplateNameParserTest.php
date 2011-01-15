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

class TemplateNameParserTest extends TestCase
{
    /**
     * @dataProvider getParseTests
     */
    public function testParse($name, $parameters)
    {
        $converter = new TemplateNameParser();

        $this->assertEquals($parameters, $converter->parse($name));
    }

    public function getParseTests()
    {
        return array(
            array('BlogBundle:Post:index.php.html', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'html'))),
            array('BlogBundle:Post:index.twig.html', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'twig', 'format' => 'html'))),
            array('BlogBundle:Post:index.php.xml', array('index', array('bundle' => 'BlogBundle', 'controller' => 'Post', 'renderer' => 'php', 'format' => 'xml'))),
        );
    }

    /**
     * @dataProvider      getParseInvalidTests
     * @expectedException \InvalidArgumentException
     */
    public function testParseInvalid($name)
    {
        $converter = new TemplateNameParser();

        $converter->parse($name);
    }

    public function getParseInvalidTests()
    {
        return array(
            array('BlogBundle:Post:index'),
            array('BlogBundle:Post'),
            array('BlogBundle:Post:foo:bar'),
            array('BlogBundle:Post:index.foo.bar.foobar'),
        );
    }
}
