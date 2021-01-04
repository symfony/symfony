<?php

class SunnyInterface_%s implements \ProxyManager\Proxy\VirtualProxyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\DummyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\SunnyInterface
{
%w  private $valueHolder%s = null;

    private $initializer%s = null;

    private static $publicProperties%s = [
%S
    ];

    public function dummy()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'dummy', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = $this->valueHolder%s->dummy()) {
            return $this;
        }

        return $returnValue;
    }

    public function & dummyRef()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'dummyRef', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = & $this->valueHolder%s->dummyRef()) {
            return $this;
        }

        return $returnValue;
    }

    public function sunny()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'sunny', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = $this->valueHolder%s->sunny()) {
            return $this;
        }

        return $returnValue;
    }

    public static function staticProxyConstructor($initializer)
    {
        static $reflection;

        $reflection = $reflection ?? new \ReflectionClass(__CLASS__);
        $instance   = $reflection->newInstanceWithoutConstructor();

        $instance->initializer%s = $initializer;

        return $instance;
    }

    public function __construct()
    {
        static $reflection;

        if (! $this->valueHolder%s) {
            $reflection = $reflection ?? new \ReflectionClass(__CLASS__);
            $this->valueHolder%s = $reflection->newInstanceWithoutConstructor();
        }
    }

    public function & __get($name)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__get', ['name' => $name], $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if (isset(self::$publicProperties%s[$name])) {
            return $this->valueHolder%s->$name;
        }

        $realInstanceReflection = new \ReflectionClass(__CLASS__);

        if (! $realInstanceReflection->hasProperty($name)) {
            $targetObject = $this->valueHolder%s;

            $backtrace = debug_backtrace(false, 1);
            trigger_error(
                sprintf(
                    'Undefined property: %%s::$%%s in %%s on line %%s',
                    $realInstanceReflection->getName(),
                    $name,
                    $backtrace[0]['file'],
                    $backtrace[0]['line']
                ),
                \E_USER_NOTICE
            );
            return $targetObject->$name;
        }

        $targetObject = $this->valueHolder%s;
        $accessor = function & () use ($targetObject, $name) {
            return $targetObject->$name;
        };
        $backtrace = debug_backtrace(true, 2);
        $scopeObject = isset($backtrace[1]['object']) ? $backtrace[1]['object'] : new \ProxyManager\Stub\EmptyClassStub();
        $accessor = $accessor->bindTo($scopeObject, get_class($scopeObject));
        $returnValue = & $accessor();

        return $returnValue;
    }

    public function __set($name, $value)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__set', array('name' => $name, 'value' => $value), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $realInstanceReflection = new \ReflectionClass(__CLASS__);

        if (! $realInstanceReflection->hasProperty($name)) {
            $targetObject = $this->valueHolder%s;

            $targetObject->$name = $value;

            return $targetObject->$name;
        }

        $targetObject = $this->valueHolder%s;
        $accessor = function & () use ($targetObject, $name, $value) {
            $targetObject->$name = $value;

            return $targetObject->$name;
        };
        $backtrace = debug_backtrace(true, 2);
        $scopeObject = isset($backtrace[1]['object']) ? $backtrace[1]['object'] : new \ProxyManager\Stub\EmptyClassStub();
        $accessor = $accessor->bindTo($scopeObject, get_class($scopeObject));
        $returnValue = & $accessor();

        return $returnValue;
    }

    public function __isset($name)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__isset', array('name' => $name), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $realInstanceReflection = new \ReflectionClass(__CLASS__);

        if (! $realInstanceReflection->hasProperty($name)) {
            $targetObject = $this->valueHolder%s;

            return isset($targetObject->$name);
        }

        $targetObject = $this->valueHolder%s;
        $accessor = function () use ($targetObject, $name) {
            return isset($targetObject->$name);
        };
        $backtrace = debug_backtrace(true, 2);
        $scopeObject = isset($backtrace[1]['object']) ? $backtrace[1]['object'] : new \ProxyManager\Stub\EmptyClassStub();
        $accessor = $accessor->bindTo($scopeObject, get_class($scopeObject));
        $returnValue = $accessor();

        return $returnValue;
    }

    public function __unset($name)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__unset', array('name' => $name), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $realInstanceReflection = new \ReflectionClass(__CLASS__);

        if (! $realInstanceReflection->hasProperty($name)) {
            $targetObject = $this->valueHolder%s;

            unset($targetObject->$name);

            return;
        }

        $targetObject = $this->valueHolder%s;
        $accessor = function () use ($targetObject, $name) {
            unset($targetObject->$name);

            return;
        };
        $backtrace = debug_backtrace(true, 2);
        $scopeObject = isset($backtrace[1]['object']) ? $backtrace[1]['object'] : new \ProxyManager\Stub\EmptyClassStub();
        $accessor = $accessor->bindTo($scopeObject, get_class($scopeObject));
        $accessor();
    }

    public function __clone()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__clone', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $this->valueHolder%s = clone $this->valueHolder%s;
    }

    public function __sleep()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__sleep', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        return array('valueHolder%s');
    }

    public function __wakeup()
    {
    }

    public function setProxyInitializer(\Closure $initializer = null)%S
    {
        $this->initializer%s = $initializer;
    }

    public function getProxyInitializer()%S
    {
        return $this->initializer%s;
    }

    public function initializeProxy() : bool
    {
        return $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'initializeProxy', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;
    }

    public function isProxyInitialized() : bool
    {
        return null !== $this->valueHolder%s;
    }

    public function getWrappedValueHolderValue()%S
    {
        return $this->valueHolder%s;
    }%w
}
