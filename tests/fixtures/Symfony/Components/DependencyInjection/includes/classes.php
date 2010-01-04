<?php

function sc_configure($instance)
{
  $instance->configure();
}

class BarClass
{
}

class BazClass
{
  public function configure($instance)
  {
    $instance->configure();
  }

  static public function getInstance()
  {
    return new self();
  }

  static public function configureStatic($instance)
  {
    $instance->configure();
  }

  static public function configureStatic1()
  {
  }
}
