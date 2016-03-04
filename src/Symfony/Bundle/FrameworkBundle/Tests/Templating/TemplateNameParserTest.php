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
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Component\Templating\TemplateReference as BaseTemplateReference;

class TemplateNameParserTest extends TestCase
{
    protected $parser;

    protected function setUp()
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnCallback(function ($bundle) {
                if (in_array($bundle, array('SensioFooBundle', 'SensioCmsFooBundle', 'FooBundle'))) {
                    return true;
                }

                throw new \InvalidArgumentException();
            }))
        ;
        $this->parser = new TemplateNameParser($kernel);
    }

    protected function tearDown()
    {
        $this->parser = null;
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($name, $logicalName, $path, $ref)
    {
        $template = $this->parser->parse($name);

        $this->assertSame($ref->getLogicalName(), $template->getLogicalName());
        $this->assertSame($logicalName, $template->getLogicalName());
        $this->assertSame($path, $template->getPath());
    }

    public function parseProvider()
    {
        return array(
            array('FooBundle:Post:index.html.php', 'FooBundle:Post:index.html.php', '@FooBundle/Resources/views/Post/index.html.php', new TemplateReference('FooBundle', 'Post', 'index', 'html', 'php')),
            array('FooBundle:Post:index.html.twig', 'FooBundle:Post:index.html.twig', '@FooBundle/Resources/views/Post/index.html.twig', new TemplateReference('FooBundle', 'Post', 'index', 'html', 'twig')),
            array('FooBundle:Post:index.xml.php', 'FooBundle:Post:index.xml.php', '@FooBundle/Resources/views/Post/index.xml.php', new TemplateReference('FooBundle', 'Post', 'index', 'xml', 'php')),
            array('SensioFooBundle:Post:index.html.php', 'SensioFooBundle:Post:index.html.php', '@SensioFooBundle/Resources/views/Post/index.html.php', new TemplateReference('SensioFooBundle', 'Post', 'index', 'html', 'php')),
            array('SensioCmsFooBundle:Post:index.html.php', 'SensioCmsFooBundle:Post:index.html.php', '@SensioCmsFooBundle/Resources/views/Post/index.html.php', new TemplateReference('SensioCmsFooBundle', 'Post', 'index', 'html', 'php')),
            array(':Post:index.html.php', ':Post:index.html.php', 'views/Post/index.html.php', new TemplateReference('', 'Post', 'index', 'html', 'php')),
            array('::index.html.php', '::index.html.php', 'views/index.html.php', new TemplateReference('', '', 'index', 'html', 'php')),
            array('index.html.php', '::index.html.php', 'views/index.html.php', new TemplateReference('', '', 'index', 'html', 'php')),
            array('FooBundle:Post:foo.bar.index.html.php', 'FooBundle:Post:foo.bar.index.html.php', '@FooBundle/Resources/views/Post/foo.bar.index.html.php', new TemplateReference('FooBundle', 'Post', 'foo.bar.index', 'html', 'php')),
            array('@FooBundle/Resources/views/layout.html.twig', '@FooBundle/Resources/views/layout.html.twig', '@FooBundle/Resources/views/layout.html.twig', new BaseTemplateReference('@FooBundle/Resources/views/layout.html.twig', 'twig')),
            array('@FooBundle/Foo/layout.html.twig', '@FooBundle/Foo/layout.html.twig', '@FooBundle/Foo/layout.html.twig', new BaseTemplateReference('@FooBundle/Foo/layout.html.twig', 'twig')),
            array('/path/to/section/index.html.php', '/path/to/section/index.html.php', '/path/to/section/index.html.php', new BaseTemplateReference('/path/to/section/index.html.php', 'php')),
            array('C:\\path\\to\\section\\name.html.php', 'C:path/to/section/name.html.php', 'C:path/to/section/name.html.php', new BaseTemplateReference('C:path/to/section/name.html.php', 'php')),
            array('C:\\path\\to\\section\\name:foo.html.php', 'C:path/to/section/name:foo.html.php', 'C:path/to/section/name:foo.html.php', new BaseTemplateReference('C:path/to/section/name:foo.html.php', 'php')),
            array('\\path\\to\\section\\name.html.php', '/path/to/section/name.html.php', '/path/to/section/name.html.php', new BaseTemplateReference('/path/to/section/name.html.php', 'php')),
            array('/path/to/section/name.php', '/path/to/section/name.php', '/path/to/section/name.php', new BaseTemplateReference('/path/to/section/name.php', 'php')),
            array('name.twig', 'name.twig', 'name.twig', new BaseTemplateReference('name.twig', 'twig')),
            array('name', 'name', 'name', new BaseTemplateReference('name')),
            array('default/index.html.php', '::default/index.html.php', 'views/default/index.html.php', new TemplateReference(null, null, 'default/index', 'html', 'php')),
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParseValidNameWithNotFoundBundle()
    {
        $this->parser->parse('BarBundle:Post:index.html.php');
    }
}
