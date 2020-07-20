<?php

class SunnyInterface_%s implements \ProxyManager\Proxy\VirtualProxyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\DummyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\SunnyInterface
{

    private $valueHolder%s = null;

    private $initializer%s = null;

    private static $publicProperties%s = [
%S
    ];

    public function dummy()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'dummy', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = $this->valueHolder%s->dummy()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public function & dummyRef()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'dummyRef', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = &$this->valueHolder%s->dummyRef()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public function sunny()
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, 'sunny', array(), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        if ($this->valueHolder%s === $returnValue = $this->valueHolder%s->sunny()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public static function staticProxyConstructor($initializer)
    {
        static $reflection;

        $reflection = $reflection ?? new \ReflectionClass(__CLASS__);
        $instance%w= $reflection->newInstanceWithoutConstructor();

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

        $targetObject = $this->valueHolder%s;

        $backtrace = debug_backtrace(false);
        trigger_error(
            sprintf(
                'Undefined property: %s::$%s in %s on line %s',
                __CLASS__,
                $name,
                $backtrace[0]['file'],
                $backtrace[0]['line']
            ),
            \E_USER_NOTICE
        );
        return $targetObject->$name;
    }

    public function __set($name, $value)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__set', array('name' => $name, 'value' => $value), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $targetObject = $this->valueHolder%s;

        return $targetObject->$name = $value;
    }

    public function __isset($name)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__isset', array('name' => $name), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $targetObject = $this->valueHolder%s;

        return isset($targetObject->$name);
    }

    public function __unset($name)
    {
        $this->initializer%s && ($this->initializer%s->__invoke($valueHolder%s, $this, '__unset', array('name' => $name), $this->initializer%s) || 1) && $this->valueHolder%s = $valueHolder%s;

        $targetObject = $this->valueHolder%s;

        unset($targetObject->$name);
return;
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
    }


}
