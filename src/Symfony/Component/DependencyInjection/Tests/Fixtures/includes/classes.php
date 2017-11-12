<?php

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\DumperInterface as ProxyDumper;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

function sc_configure($instance): void
{
    $instance->configure();
}

class BarClass
{
    protected $baz;
    public $foo = 'foo';

    public function setBaz(BazClass $baz): void
    {
        $this->baz = $baz;
    }

    public function getBaz()
    {
        return $this->baz;
    }
}

class BazClass
{
    protected $foo;

    public function setFoo(Foo $foo): void
    {
        $this->foo = $foo;
    }

    public function configure($instance): void
    {
        $instance->configure();
    }

    public static function getInstance()
    {
        return new self();
    }

    public static function configureStatic($instance): void
    {
        $instance->configure();
    }

    public static function configureStatic1(): void
    {
    }
}

class BarUserClass
{
    public $bar;

    public function __construct(BarClass $bar)
    {
        $this->bar = $bar;
    }
}

class MethodCallClass
{
    public $simple;
    public $complex;
    private $callPassed = false;

    public function callMe(): void
    {
        $this->callPassed = is_scalar($this->simple) && is_object($this->complex);
    }

    public function callPassed()
    {
        return $this->callPassed;
    }
}

class DummyProxyDumper implements ProxyDumper
{
    public function isProxyCandidate(Definition $definition)
    {
        return false;
    }

    public function getProxyFactoryCode(Definition $definition, $id, $factoryCall = null)
    {
        return '';
    }

    public function getProxyCode(Definition $definition)
    {
        return '';
    }
}

class LazyContext
{
    public $lazyValues;
    public $lazyEmptyValues;

    public function __construct($lazyValues, $lazyEmptyValues)
    {
        $this->lazyValues = $lazyValues;
        $this->lazyEmptyValues = $lazyEmptyValues;
    }
}

class FoobarCircular
{
    public function __construct(FooCircular $foo)
    {
        $this->foo = $foo;
    }
}

class FooCircular
{
    public function __construct(BarCircular $bar)
    {
        $this->bar = $bar;
    }
}

class BarCircular
{
    public function addFoobar(FoobarCircular $foobar): void
    {
        $this->foobar = $foobar;
    }
}
