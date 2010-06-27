<?php

namespace Symfony\Tests\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Mapping\ClassMetadata;
use Symfony\Components\Validator\Mapping\Loader\LoaderChain;

class LoaderChainTest extends \PHPUnit_Framework_TestCase
{
    public function testAllLoadersAreCalled()
    {
        $metadata = new ClassMetadata('\stdClass');

        $loader1 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
        $loader1->expects($this->once())
                        ->method('loadClassMetadata')
                        ->with($this->equalTo($metadata));

        $loader2 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
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

        $loader1 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
        $loader1->expects($this->any())
                        ->method('loadClassMetadata')
                        ->will($this->returnValue(true));

        $loader2 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
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

        $loader1 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
        $loader1->expects($this->any())
                        ->method('loadClassMetadata')
                        ->will($this->returnValue(false));

        $loader2 = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
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

