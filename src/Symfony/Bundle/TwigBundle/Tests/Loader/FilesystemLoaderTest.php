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

use Symfony\Bundle\TwigBundle\Loader\FilesystemLoader;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\Templating\TemplateReferenceInterface;

/**
 * @group legacy
 */
class FilesystemLoaderTest extends TestCase
{
    public function testGetSourceContext()
    {
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willReturn(__DIR__.'/../DependencyInjection/Fixtures/templates/layout.html.twig')
        ;
        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/templates', 'namespace');

        // Twig-style
        $this->assertEquals("This is a layout\n", $loader->getSourceContext('@namespace/layout.html.twig')->getCode());

        // Symfony-style
        $this->assertEquals("This is a layout\n", $loader->getSourceContext('TwigBundle::layout.html.twig')->getCode());
    }

    public function testExists()
    {
        // should return true for templates that Twig does not find, but Symfony does
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willReturn($template = __DIR__.'/../DependencyInjection/Fixtures/templates/layout.html.twig')
        ;
        $loader = new FilesystemLoader($locator, $parser);

        $this->assertTrue($loader->exists($template));
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     */
    public function testTwigErrorIfLocatorThrowsInvalid()
    {
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->willReturn($this->getMockBuilder(TemplateReferenceInterface::class)->getMock())
        ;

        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willThrowException(new \InvalidArgumentException('Unable to find template "NonExistent".'))
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     */
    public function testTwigErrorIfLocatorReturnsFalse()
    {
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->willReturn($this->getMockBuilder(TemplateReferenceInterface::class)->getMock())
        ;

        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willReturn(false)
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    /**
     * @expectedException \Twig\Error\LoaderError
     * @expectedExceptionMessageRegExp /Unable to find template "name\.format\.engine" \(looked into: .*Tests.Loader.\.\..DependencyInjection.Fixtures.templates\)/
     */
    public function testTwigErrorIfTemplateDoesNotExist()
    {
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/templates');

        $method = new \ReflectionMethod('Symfony\Bundle\TwigBundle\Loader\FilesystemLoader', 'findTemplate');
        $method->setAccessible(true);
        $method->invoke($loader, 'name.format.engine');
    }

    public function testTwigSoftErrorIfTemplateDoesNotExist()
    {
        $parser = $this->getMockBuilder('Symfony\Component\Templating\TemplateNameParserInterface')->getMock();
        $locator = $this->getMockBuilder('Symfony\Component\Config\FileLocatorInterface')->getMock();

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/templates');

        $method = new \ReflectionMethod('Symfony\Bundle\TwigBundle\Loader\FilesystemLoader', 'findTemplate');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($loader, 'name.format.engine', false));
    }
}
