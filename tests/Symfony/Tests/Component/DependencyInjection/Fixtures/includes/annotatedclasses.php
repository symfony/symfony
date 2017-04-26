<?php

use Symfony\Component\DependencyInjection\Annotation\Inject;

class SimpleFooClass 
{

    private $foo, $moo;

    public $bar = null, $initialized = false, $configured = false, $called = false, $arguments = array();

    public function __construct($arguments = array())
    {
        $this->arguments = $arguments;
    }

    static public function getInstance($arguments = array())
    {
        $obj = new self($arguments);
        $obj->called = true;

        return $obj;
    }

    public function initialize()
    {
        $this->initialized = true;
    }

    public function configure()
    {
        $this->configured = true;
    }

    public function setBar($value = null)
    {
        $this->bar = $value;
    }    
}
class SimpleBarClass {}

class FooAnnotatedClass
{
    /**
     * @Inject("foo")
     */
    private $foo;
    
    
    /**
     * @return SimpleFooClass 
     */
    public function getFoo()
    {
        return $this->foo;
    }
}

class BarAnnotatedClass
{
    
    /**
     * @Inject(source="bar")
     */
    private $bar;
    
     /**
     * @return SimpleBarClass 
     */
    public function getBar()
    {
        return $this->bar;
    }

    
    
}

class FooBarAnnotatedClass
{
    
   /**
     * @Inject("annoted.bar")
     */
    private $bar;
    
    
    /**
     * @Inject("annoted.foo")
     */
    private $foo;
    
    
    /**
     * @return BarAnnotatedClass 
     */
    public function getBarAnnoted()
    {
        return $this->bar;
    }

    /**
     * @return FooAnnotatedClass 
     */
    public function getFooAnnoted()
    {
        return $this->foo;
    }

}


class NotExistentServiceAnnotatedClass
{
 
    /**
     * @Inject("invalid.bar")
     */
    private $bar;
    
    /**
     * @Inject("invalid.foo")
     */
    private $foo;
}