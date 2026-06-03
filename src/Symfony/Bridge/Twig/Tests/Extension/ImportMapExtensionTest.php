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
use Symfony\Bridge\Twig\Extension\ImportMapExtension;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

class ImportMapExtensionTest extends TestCase
{
    public function testItRendersTheImportmap()
    {
        $twig = new Environment(new ArrayLoader([
            'template' => '{{ importmap("application") }}',
        ]), ['debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new ImportMapExtension());
        $importMapRenderer = $this->createMock(ImportMapRenderer::class);
        $expected = '<script type="importmap">{ "imports": {}}</script>';
        $importMapRenderer->expects($this->once())
            ->method('render')
            ->with('application')
            ->willReturn($expected);
        $runtime = new ImportMapRuntime($importMapRenderer);

        $runtimeLoader = new ContainerRuntimeLoader(new ServiceLocator([
            ImportMapRuntime::class => static fn () => $runtime,
        ]));
        $twig->addRuntimeLoader($runtimeLoader);

        $this->assertSame($expected, $twig->render('template'));
    }
}
