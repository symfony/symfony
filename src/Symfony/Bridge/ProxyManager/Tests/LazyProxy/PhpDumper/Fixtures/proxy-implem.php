<?php

class SunnyInterface_1eff735 implements \ProxyManager\Proxy\VirtualProxyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\DummyInterface, \Symfony\Bridge\ProxyManager\Tests\LazyProxy\PhpDumper\SunnyInterface
{

    private $valueHolder1eff735 = null;

    private $initializer1eff735 = null;

    private static $publicProperties1eff735 = [
        
    ];

    public function dummy()
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, 'dummy', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        if ($this->valueHolder1eff735 === $returnValue = $this->valueHolder1eff735->dummy()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public function & dummyRef()
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, 'dummyRef', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        if ($this->valueHolder1eff735 === $returnValue = &$this->valueHolder1eff735->dummyRef()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public function sunny()
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, 'sunny', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        if ($this->valueHolder1eff735 === $returnValue = $this->valueHolder1eff735->sunny()) {
            $returnValue = $this;
        }

        return $returnValue;
    }

    public static function staticProxyConstructor($initializer)
    {
        static $reflection;

        $reflection = $reflection ?? new \ReflectionClass(__CLASS__);
        $instance = $reflection->newInstanceWithoutConstructor();

        $instance->initializer1eff735 = $initializer;

        return $instance;
    }

    public function __construct()
    {
        static $reflection;

        if (! $this->valueHolder1eff735) {
            $reflection = $reflection ?? new \ReflectionClass(__CLASS__);
            $this->valueHolder1eff735 = $reflection->newInstanceWithoutConstructor();
        }
    }

    public function & __get($name)
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__get', ['name' => $name], $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        if (isset(self::$publicProperties1eff735[$name])) {
            return $this->valueHolder1eff735->$name;
        }

        $targetObject = $this->valueHolder1eff735;

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
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__set', array('name' => $name, 'value' => $value), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        $targetObject = $this->valueHolder1eff735;

        return $targetObject->$name = $value;
    }

    public function __isset($name)
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__isset', array('name' => $name), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        $targetObject = $this->valueHolder1eff735;

        return isset($targetObject->$name);
    }

    public function __unset($name)
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__unset', array('name' => $name), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        $targetObject = $this->valueHolder1eff735;

        unset($targetObject->$name);
return;
    }

    public function __clone()
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__clone', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        $this->valueHolder1eff735 = clone $this->valueHolder1eff735;
    }

    public function __sleep()
    {
        $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, '__sleep', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;

        return array('valueHolder1eff735');
    }

    public function __wakeup()
    {
    }

    public function setProxyInitializer(\Closure $initializer = null)
    {
        $this->initializer1eff735 = $initializer;
    }

    public function getProxyInitializer()
    {
        return $this->initializer1eff735;
    }

    public function initializeProxy() : bool
    {
        return $this->initializer1eff735 && ($this->initializer1eff735->__invoke($valueHolder1eff735, $this, 'initializeProxy', array(), $this->initializer1eff735) || 1) && $this->valueHolder1eff735 = $valueHolder1eff735;
    }

    public function isProxyInitialized() : bool
    {
        return null !== $this->valueHolder1eff735;
    }

    public function getWrappedValueHolderValue()
    {
        return $this->valueHolder1eff735;
    }


}
