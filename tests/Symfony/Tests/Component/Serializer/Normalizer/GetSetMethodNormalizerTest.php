<?php

namespace Symfony\Tests\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class GetSetMethodNormalizerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->normalizer = new GetSetMethodNormalizer;
        $this->normalizer->setSerializer($this->getMock('Symfony\Component\Serializer\Serializer'));
    }

    public function testNormalize()
    {
        $obj = new GetSetDummy;
        $obj->setFoo('foo');
        $obj->setBar('bar');
        $this->assertEquals(
            array('foo' => 'foo', 'bar' => 'bar', 'foobar' => 'foobar'),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar', 'foobar' => 'foobar'),
            __NAMESPACE__.'\GetSetDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar', 'foobar' => 'foobar'),
            __NAMESPACE__.'\GetConstructorDummy', 'any');
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }
}

class GetSetDummy
{
    protected $foo;
    private $bar;

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    public function getFooBar()
    {
        return $this->foo . $this->bar;
    }

    public function otherMethod()
    {
        throw new \RuntimeException("Dummy::otherMethod() should not be called");
    }
}

class GetConstructorDummy
{
    protected $foo;
    private $bar;

    public function __construct($foo, $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function otherMethod()
    {
        throw new \RuntimeException("Dummy::otherMethod() should not be called");
    }
}
