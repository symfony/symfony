<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\Tests\Loader;

use Symphony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symphony\Bundle\TwigBundle\Loader\FilesystemLoader;
use Symphony\Bundle\TwigBundle\Tests\TestCase;

class FilesystemLoaderTest extends TestCase
{
    public function testGetSourceContext()
    {
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->will($this->returnValue(__DIR__.'/../DependencyInjection/Fixtures/Resources/views/layout.html.twig'))
        ;
        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/Resources/views', 'namespace');

        // Twig-style
        $this->assertEquals("This is a layout\n", $loader->getSourceContext('@namespace/layout.html.twig')->getCode());

        // Symphony-style
        $this->assertEquals("This is a layout\n", $loader->getSourceContext('TwigBundle::layout.html.twig')->getCode());
    }

    public function testExists()
    {
        // should return true for templates that Twig does not find, but Symphony does
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->will($this->returnValue($template = __DIR__.'/../DependencyInjection/Fixtures/Resources/views/layout.html.twig'))
        ;
        $loader = new FilesystemLoader($locator, $parser);

        $this->assertTrue($loader->exists($template));
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     */
    public function testTwigErrorIfLocatorThrowsInvalid()
    {
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->will($this->returnValue(new TemplateReference('', '', 'name', 'format', 'engine')))
        ;

        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->will($this->throwException(new \InvalidArgumentException('Unable to find template "NonExistent".')))
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     */
    public function testTwigErrorIfLocatorReturnsFalse()
    {
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->will($this->returnValue(new TemplateReference('', '', 'name', 'format', 'engine')))
        ;

        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->will($this->returnValue(false))
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     * @expectedExceptionMessageRegExp /Unable to find template "name\.format\.engine" \(looked into: .*Tests.Loader.\.\..DependencyInjection.Fixtures.Resources.views\)/
     */
    public function testTwigErrorIfTemplateDoesNotExist()
    {
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/Resources/views');

        $method = new \ReflectionMethod('Symphony\Bundle\TwigBundle\Loader\FilesystemLoader', 'findTemplate');
        $method->setAccessible(true);
        $method->invoke($loader, 'name.format.engine');
    }

    public function testTwigSoftErrorIfTemplateDoesNotExist()
    {
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symphony\Component\Config\FileLocatorInterface')->getMock();

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/Resources/views');

        $method = new \ReflectionMethod('Symphony\Bundle\TwigBundle\Loader\FilesystemLoader', 'findTemplate');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($loader, 'name.format.engine', false));
    }
}
