<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormRendererInterface;
use Twig\Environment;

class FormExtensionTest extends TestCase
{
    /**
     * @dataProvider rendererDataProvider
     */
    public function testInitRuntimeAndAccessRenderer($rendererConstructor, $expectedAccessedRenderer)
    {
        $extension = new FormExtension($rendererConstructor);
        $extension->initRuntime($this->createMock(Environment::class));
        $this->assertSame($expectedAccessedRenderer, $extension->renderer);
    }

    /**
     * @dataProvider rendererDataProvider
     */
    public function testAccessRendererAndInitRuntime($rendererConstructor, $expectedAccessedRenderer)
    {
        $extension = new FormExtension($rendererConstructor);
        $this->assertSame($expectedAccessedRenderer, $extension->renderer);
        $extension->initRuntime($this->createMock(Environment::class));
    }

    public function rendererDataProvider()
    {
        $twigRenderer = $this->createMock(TwigRendererInterface::class);
        $twigRenderer->expects($this->once())
            ->method('setEnvironment');

        yield array($twigRenderer, $twigRenderer);

        $twigRenderer = $this->createMock(TwigRendererInterface::class);
        $twigRenderer->expects($this->once())
            ->method('setEnvironment');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('service_id')
            ->willReturn($twigRenderer);

        yield array(array($container, 'service_id'), $twigRenderer);

        $formRenderer = $this->createMock(FormRendererInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('service_id')
            ->willReturn($formRenderer);

        yield array(array($container, 'service_id'), $formRenderer);
    }
}
