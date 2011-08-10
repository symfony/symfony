<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Loader;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Component\Templating\TemplateNameParserInterface;
use InvalidArgumentException;

class FilesystemLoaderTest extends TestCase
{
    /** @var FileLocatorInterface */
    private $locator;
    /** @var TemplateNameParserInterface */
    private $parser;
    /** @var FilesystemLoader */
    private $loader;

    protected function setUp()
    {
        parent::setUp();

        $this->locator = $this->getMock('Symfony\Component\Config\FileLocatorInterface');
        $this->parser = $this->getMock('Symfony\Component\Templating\TemplateNameParserInterface');
        $this->loader = new FilesystemLoader($this->locator, $this->parser);

        $this->parser->expects($this->once())
                ->method('parse')
                ->with('name.format.engine')
                ->will($this->returnValue(new TemplateReference('', '', 'name', 'format', 'engine')))
        ;
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->locator = null;
        $this->parser = null;
        $this->loader = null;
    }

    public function testTwigErrorIfLocatorThrowsInvalid()
    {
        $this->setExpectedException('Twig_Error_Loader');
        $invalidException = new InvalidArgumentException('Unable to find template "NonExistent".');
        $this->locator->expects($this->once())
                      ->method('locate')
                      ->will($this->throwException($invalidException));

        $this->loader->getCacheKey('name.format.engine');
    }

    public function testTwigErrorIfLocatorReturnsFalse()
    {
        $this->setExpectedException('Twig_Error_Loader');
        $this->locator->expects($this->once())
                      ->method('locate')
                      ->will($this->returnValue(false));

        $this->loader->getCacheKey('name.format.engine');
    }
}
