<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Mapping\ClassMetadata;
use Symphony\Component\Validator\Mapping\Loader\LoaderChain;

class LoaderChainTest extends TestCase
{
    public function testAllLoadersAreCalled()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader1->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $loader2 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader2->expects($this->once())
            ->method('loadClassMetadata')
            ->with($this->equalTo($metadata));

        $chain = new LoaderChain(array(
            $loader1,
            $loader2,
        ));

        $chain->loadClassMetadata($metadata);
    }

    public function testReturnsTrueIfAnyLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader1->expects($this->any())
            ->method('loadClassMetadata')
            ->will($this->returnValue(true));

        $loader2 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader2->expects($this->any())
            ->method('loadClassMetadata')
            ->will($this->returnValue(false));

        $chain = new LoaderChain(array(
            $loader1,
            $loader2,
        ));

        $this->assertTrue($chain->loadClassMetadata($metadata));
    }

    public function testReturnsFalseIfNoLoaderReturnedTrue()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader1->expects($this->any())
            ->method('loadClassMetadata')
            ->will($this->returnValue(false));

        $loader2 = $this->getMockBuilder('Symphony\Component\Validator\Mapping\Loader\LoaderInterface')->getMock();
        $loader2->expects($this->any())
            ->method('loadClassMetadata')
            ->will($this->returnValue(false));

        $chain = new LoaderChain(array(
            $loader1,
            $loader2,
        ));

        $this->assertFalse($chain->loadClassMetadata($metadata));
    }
}
