<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\ImportMapExtension;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

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

        $mockRuntimeLoader = $this->createMock(RuntimeLoaderInterface::class);
        $mockRuntimeLoader
            ->method('load')
            ->willReturnMap([
                [ImportMapRuntime::class, $runtime],
            ])
        ;
        $twig->addRuntimeLoader($mockRuntimeLoader);

        $this->assertSame($expected, $twig->render('template'));
    }
}
