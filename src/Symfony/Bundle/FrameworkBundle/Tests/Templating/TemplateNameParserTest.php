<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Kernel;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;

class TemplateNameParserTest extends TestCase
{
    protected $parser;

    protected function  setUp()
    {
        $kernel = new Kernel();
        $kernel->boot();

        $this->parser = new TemplateNameParser($kernel);
    }

    protected function tearDown()
    {
        unset($this->parser);
    }

    /**
     * @dataProvider getLogicalNameToTemplateProvider
     */
    public function testParse($name, $ref)
    {
        $template = $this->parser->parse($name);

        $this->assertEquals($template->getSignature(), $ref->getSignature());
    }

    public function getLogicalNameToTemplateProvider()
    {
        return array(
            array('Foo:Post:index.html.php', new TemplateReference('Foo', 'Post', 'index', 'html', 'php')),
            array('Foo:Post:index.html.twig', new TemplateReference('Foo', 'Post', 'index', 'html', 'twig')),
            array('Foo:Post:index.xml.php', new TemplateReference('Foo', 'Post', 'index', 'xml', 'php')),
            array('SensioFoo:Post:index.html.php', new TemplateReference('SensioFoo', 'Post', 'index', 'html', 'php')),
            array('SensioCmsFoo:Post:index.html.php', new TemplateReference('SensioCmsFoo', 'Post', 'index', 'html', 'php')),
            array(':Post:index.html.php', new TemplateReference('', 'Post', 'index', 'html', 'php')),
            array('::index.html.php', new TemplateReference('', '', 'index', 'html', 'php')),
        );
    }

    /**
     * @dataProvider      getInvalidLogicalNameProvider
     * @expectedException \InvalidArgumentException
     */
    public function testParseInvalidName($name)
    {
        $this->parser->parse($name);
    }

    public function getInvalidLogicalNameProvider()
    {
        return array(
            array('Bar:Post:index.html.php'),
            array('Foo:Post:index'),
            array('FooBundle:Post'),
            array('Foo:Post:foo:bar'),
            array('Foo:Post:index.foo.bar.foobar'),
        );
    }

    /**
     * @dataProvider getFilenameToTemplateProvider
     */
    public function testParseFromFilename($file, $ref)
    {
        $template = $this->parser->parseFromFilename($file);
        
        if ($ref === false) {
            $this->assertFalse($template);
        } else {
            $this->assertEquals($template->getSignature(), $ref->getSignature());
        }
    }

    public function getFilenameToTemplateProvider()
    {
        return array(
            array('/path/to/section/name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')),
            array('\\path\\to\\section\\name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')),
            array('name.format.engine', new TemplateReference('', '', 'name', 'format', 'engine')),
            array('name.format', false),
            array('name', false),
        );
    }

}
