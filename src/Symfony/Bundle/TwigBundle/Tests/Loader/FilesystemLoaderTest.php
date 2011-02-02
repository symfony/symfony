<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Loader;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocatorInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use InvalidArgumentException;

class FilesystemLoaderTest extends TestCase
{
    /** @var TemplateLocatorInterface */
    private $locator;
    /** @var TemplateNameParserInterface */
    private $parser;
    /** @var FilesystemLoader */
    private $loader;

    protected function setUp()
    {
        parent::setUp();

        $this->locator = $this->getMock('Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocatorInterface');
        $this->parser = $this->getMock('Symfony\Component\Templating\TemplateNameParserInterface');
        $this->loader = new FilesystemLoader($this->locator, $this->parser);
    }

    public function testTwigErrorIfLocatorThrowsInvalid()
    {
        $this->setExpectedException('Twig_Error_Loader');
        $invalidException = new InvalidArgumentException('Unable to find template "NonExistent".');
        $this->locator->expects($this->once())
                      ->method('locate')
                      ->will($this->throwException($invalidException));

        $this->loader->getCacheKey('NonExistent');
    }

    public function testTwigErrorIfLocatorReturnsFalse()
    {
        $this->setExpectedException('Twig_Error_Loader');
        $this->locator->expects($this->once())
                      ->method('locate')
                      ->will($this->returnValue(false));

        $this->loader->getCacheKey('NonExistent');
    }
}
