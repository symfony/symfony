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
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Twig\Error\LoaderError;

/**
 * @group legacy
 */
class FilesystemLoaderTest extends TestCase
{
    public function testGetSourceContext()
    {
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $locator = $this->createMock(FileLocatorInterface::class);
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
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $locator = $this->createMock(FileLocatorInterface::class);
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willReturn($template = __DIR__.'/../DependencyInjection/Fixtures/templates/layout.html.twig')
        ;
        $loader = new FilesystemLoader($locator, $parser);

        $this->assertTrue($loader->exists($template));
    }

    public function testTwigErrorIfLocatorThrowsInvalid()
    {
        $this->expectException(LoaderError::class);
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->willReturn($this->createMock(TemplateReferenceInterface::class))
        ;

        $locator = $this->createMock(FileLocatorInterface::class);
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willThrowException(new \InvalidArgumentException('Unable to find template "NonExistent".'))
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    public function testTwigErrorIfLocatorReturnsFalse()
    {
        $this->expectException(LoaderError::class);
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with('name.format.engine')
            ->willReturn($this->createMock(TemplateReferenceInterface::class))
        ;

        $locator = $this->createMock(FileLocatorInterface::class);
        $locator
            ->expects($this->once())
            ->method('locate')
            ->willReturn(false)
        ;

        $loader = new FilesystemLoader($locator, $parser);
        $loader->getCacheKey('name.format.engine');
    }

    public function testTwigErrorIfTemplateDoesNotExist()
    {
        $this->expectException(LoaderError::class);
        $this->expectExceptionMessageMatches('/Unable to find template "name\.format\.engine" \(looked into: .*Tests.Loader.\.\..DependencyInjection.Fixtures.templates\)/');
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $locator = $this->createMock(FileLocatorInterface::class);

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/templates');

        $method = new \ReflectionMethod(FilesystemLoader::class, 'findTemplate');
        $method->setAccessible(true);
        $method->invoke($loader, 'name.format.engine');
    }

    public function testTwigSoftErrorIfTemplateDoesNotExist()
    {
        $parser = $this->createMock(TemplateNameParserInterface::class);
        $locator = $this->createMock(FileLocatorInterface::class);

        $loader = new FilesystemLoader($locator, $parser);
        $loader->addPath(__DIR__.'/../DependencyInjection/Fixtures/templates');

        $method = new \ReflectionMethod(FilesystemLoader::class, 'findTemplate');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($loader, 'name.format.engine', false));
    }
}
