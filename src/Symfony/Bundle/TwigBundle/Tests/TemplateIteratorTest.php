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
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface');
        $bundle->expects($this->any())->method('getName')->will($this->returnValue('BarBundle'));
        $bundle->expects($this->any())->method('getPath')->will($this->returnValue(__DIR__.'/Fixtures/templates/BarBundle'));

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
        $kernel->expects($this->any())->method('getBundles')->will($this->returnValue(array(
            $bundle,
        )));
        $iterator = new TemplateIterator($kernel, __DIR__.'/Fixtures/templates', array(__DIR__.'/Fixtures/templates/Foo' => 'Foo'));

        $sorted = iterator_to_array($iterator);
        sort($sorted);
        $this->assertEquals(
            array(
                '@Bar/index.html.twig',
                '@Foo/index.html.twig',
                'layout.html.twig',
                'sub/sub.html.twig',
            ),
            $sorted
        );
    }
}
