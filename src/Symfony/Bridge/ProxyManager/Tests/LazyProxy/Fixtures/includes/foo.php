<?php

class ProxyManagerBridgeFooClass
{
    public static $destructorCount = 0;

    public $foo;
    public $moo;

    public $bar = null;
    public $initialized = false;
    public $configured = false;
    public $called = false;
    public $arguments = array();

    public function __construct($arguments = array())
    {
        $this->arguments = $arguments;
    }

    public static function getInstance($arguments = array())
    {
        $obj = new self($arguments);
        $obj->called = true;

        return $obj;
    }

    public function initialize(): void
    {
        $this->initialized = true;
    }

    public function configure(): void
    {
        $this->configured = true;
    }

    public function setBar($value = null): void
    {
        $this->bar = $value;
    }

    public function __destruct()
    {
        ++self::$destructorCount;
    }
}
