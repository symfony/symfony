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

class TemplateIteratorTest extends TestCase
{
    public function testGetIterator()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')->getMock();
        $bundle->expects($this->any())->method('getName')->willReturn('BarBundle');
        $bundle->expects($this->any())->method('getPath')->willReturn(__DIR__.'/Fixtures/templates/BarBundle');

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            $bundle,
        ]);
        $patternFiles = ['/.*(?<!\.txt)$/']; // excluding *.txt files
        $excludePaths = ['bundles', 'node_modules'];
        $iterator = new TemplateIterator($kernel, [__DIR__.'/Fixtures/templates/Foo' => 'Foo'], __DIR__.'/DependencyInjection/Fixtures/templates', $patternFiles, $excludePaths);

        $sorted = iterator_to_array($iterator);
        sort($sorted);
        $this->assertEquals(
            [
                '@Bar/index.html.twig',
                '@Bar/layout.html.twig',
                '@Foo/index.html.twig',
                'layout.html.twig',
            ],
            $sorted
        );
    }
}
