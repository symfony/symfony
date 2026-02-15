<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\TwigBundle\TemplateIterator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

class TemplateIteratorTest extends TestCase
{
    public function testGetIterator()
    {
        $iterator = new TemplateIterator($this->createKernelMock(), [__DIR__.'/Fixtures/templates/Foo' => 'Foo'], __DIR__.'/DependencyInjection/Fixtures/templates');

        $sorted = iterator_to_array($iterator);
        sort($sorted);
        $this->assertEquals(
            [
                '@!Bar/index.html.twig',
                '@Bar/index.html.twig',
                '@Bar/layout.html.twig',
                '@Foo/index.html.twig',
                '@Foo/not-twig.js',
                'layout.html.twig',
            ],
            $sorted
        );
    }

    public function testGetIteratorWithFileNameFilter()
    {
        $iterator = new TemplateIterator($this->createKernelMock(), [__DIR__.'/Fixtures/templates/Foo' => 'Foo'], __DIR__.'/DependencyInjection/Fixtures/templates', ['*.twig']);

        $sorted = iterator_to_array($iterator);
        sort($sorted);
        $this->assertEquals(
            [
                '@!Bar/index.html.twig',
                '@Bar/index.html.twig',
                '@Bar/layout.html.twig',
                '@Foo/index.html.twig',
                'layout.html.twig',
            ],
            $sorted
        );
    }

    private function createKernelMock(): Kernel
    {
        $bundle = $this->createStub(BundleInterface::class);
        $bundle->method('getName')->willReturn('BarBundle');
        $bundle->method('getPath')->willReturn(__DIR__.'/Fixtures/templates/BarBundle');

        $kernel = $this->createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            $bundle,
        ]);

        return $kernel;
    }
}
