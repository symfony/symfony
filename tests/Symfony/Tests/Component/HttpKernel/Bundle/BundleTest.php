<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\Bundle;

class BundleTest extends \PHPUnit_Framework_TestCase
{
    public function testGetNormalizedPathReturnsANormalizedPath()
    {
        $bundle = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')
            ->setMethods(array('getPath'))
            ->disableOriginalConstructor()
            ->getMockForAbstractClass()
        ;

        $bundle
            ->expects($this->once())
            ->method('getPath')
            ->will($this->returnValue('path\\to\\foo\\bar'))
        ;

        $this->assertEquals('path/to/foo/bar', $bundle->getNormalizedPath());
    }
}
