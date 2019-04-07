<?php

namespace Symfony\Component\Serializer\Tests\Instantiator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Instantiator\Instantiator;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class InstantiatorTest extends TestCase
{
    public function testInstantiate()
    {
        $instantiator = new Instantiator();
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];

        $dummy = $instantiator->instantiate(DummyWithoutConstructor::class, $data);

        $this->assertInstanceOf(DummyWithoutConstructor::class, $dummy);
    }

    public function testInstantiateWithConstructor()
    {
        $instantiator = new Instantiator();
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];

        $dummy = $instantiator->instantiate(DummyWithConstructor::class, $data);

        $this->assertInstanceOf(DummyWithConstructor::class, $dummy);
        $this->assertSame('foo', $dummy->foo);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException
     * @expectedExceptionMessage Cannot create an instance of Symfony\Component\Serializer\Tests\Instantiator\DummyWithExtraConstructor from serialized data because its constructor requires parameter "extra" to be present.
     */
    public function testCannotInstantiate()
    {
        $instantiator = new Instantiator();
        $data = ['foo' => 'foo'];

        $instantiator->instantiate(DummyWithExtraConstructor::class, $data);
    }

    public function testInstantiateWithDefaultArguments()
    {
        $instantiator = new Instantiator();
        $data = ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'];

        $dummy = $instantiator->instantiate(DummyWithExtraConstructor::class, $data, null, [
            AbstractObjectNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                DummyWithExtraConstructor::class => ['extra' => 'extraData'],
            ],
        ]);

        $this->assertInstanceOf(DummyWithExtraConstructor::class, $dummy);
        $this->assertSame('foo', $dummy->foo);
        $this->assertSame('extraData', $dummy->extra);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException
     * @expectedExceptionMessage Could not create object of class "Symfony\Component\Serializer\Tests\Instantiator\DummyBar" of the parameter "bar".
     */
    public function testInstantiateWithDenormalizationAndDenormalizer()
    {
        $instantiator = new Instantiator();
        $data = ['foo' => 'foo', 'bar' => ['baz' => 'baz']];

        $dummy = $instantiator->instantiate(DummyWithObjectArgument::class, $data);

        $this->assertInstanceOf(DummyWithExtraConstructor::class, $dummy);
        $this->assertSame('foo', $dummy->foo);
        $this->assertSame('extraData', $dummy->extra);
    }

    public function testInstantiateWithDenormalization()
    {
        $instantiator = new Instantiator();
        $instantiator->setDenormalizer(new ObjectNormalizer());

        $data = ['foo' => 'foo', 'bar' => ['baz' => 'baz']];

        $dummy = $instantiator->instantiate(DummyWithObjectArgument::class, $data);

        $this->assertInstanceOf(DummyWithObjectArgument::class, $dummy);
        $this->assertSame('foo', $dummy->foo);
        $this->assertInstanceOf(DummyBar::class, $dummy->bar);
    }
}

class DummyWithoutConstructor
{
    public $foo;
    public $bar;
    public $baz;
}

class DummyWithConstructor
{
    public $foo;
    public $bar;
    public $quz;

    public function __construct($foo)
    {
        $this->foo = $foo;
    }
}

class DummyWithExtraConstructor
{
    public $foo;
    public $bar;
    public $quz;
    public $extra;

    public function __construct($foo, $extra)
    {
        $this->foo = $foo;
        $this->extra = $extra;
    }
}

class DummyWithObjectArgument
{
    public $foo;
    public $bar;

    public function __construct($foo, DummyBar $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}

class DummyBar
{
    public $baz;
}
