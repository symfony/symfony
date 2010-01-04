<?php

use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

/**
 * ProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @property FooClass $foo
 * @property FooClass $bar
 * @property Object $foo.baz
 * @property Object $foo_bar
 * @property FooClass $method_call1
 * @property FooClass $alias_for_foo
 */
class ProjectServiceContainer extends Container
{
  protected $shared = array();

  /**
   * Constructor.
   */
  public function __construct()
  {
    parent::__construct($this->getDefaultParameters());
  }

  /**
   * Gets the 'foo' service.
   *
   * @return FooClass A FooClass instance.
   */
  protected function getFooService()
  {
    require_once '%path%/foo.php';

    $instance = call_user_func(array('FooClass', 'getInstance'), 'foo', $this->getService('foo.baz'), array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo'), 'bar' => $this->getParameter('foo')), true, $this);
    $instance->setBar('bar');
    $instance->initialize();
    sc_configure($instance);

    return $instance;
  }

  /**
   * Gets the 'bar' service.
   *
   * This service is shared.
   * This method always returns the same instance of the service.
   *
   * @return FooClass A FooClass instance.
   */
  protected function getBarService()
  {
    if (isset($this->shared['bar'])) return $this->shared['bar'];

    $instance = new FooClass('foo', $this->getService('foo.baz'), $this->getParameter('foo_bar'));
    $this->getService('foo.baz')->configure($instance);

    return $this->shared['bar'] = $instance;
  }

  /**
   * Gets the 'foo.baz' service.
   *
   * This service is shared.
   * This method always returns the same instance of the service.
   *
   * @return Object A %baz_class% instance.
   */
  protected function getFoo_BazService()
  {
    if (isset($this->shared['foo.baz'])) return $this->shared['foo.baz'];

    $instance = call_user_func(array($this->getParameter('baz_class'), 'getInstance'));
    call_user_func(array($this->getParameter('baz_class'), 'configureStatic1'), $instance);

    return $this->shared['foo.baz'] = $instance;
  }

  /**
   * Gets the 'foo_bar' service.
   *
   * This service is shared.
   * This method always returns the same instance of the service.
   *
   * @return Object A %foo_class% instance.
   */
  protected function getFooBarService()
  {
    if (isset($this->shared['foo_bar'])) return $this->shared['foo_bar'];

    $class = $this->getParameter('foo_class');
    $instance = new $class();

    return $this->shared['foo_bar'] = $instance;
  }

  /**
   * Gets the 'method_call1' service.
   *
   * This service is shared.
   * This method always returns the same instance of the service.
   *
   * @return FooClass A FooClass instance.
   */
  protected function getMethodCall1Service()
  {
    if (isset($this->shared['method_call1'])) return $this->shared['method_call1'];

    $instance = new FooClass();
    $instance->setBar($this->getService('foo'));
    $instance->setBar($this->getService('foo', Container::NULL_ON_INVALID_REFERENCE));
    if ($this->hasService('foo'))
    {
      $instance->setBar($this->getService('foo', Container::NULL_ON_INVALID_REFERENCE));
    }
    if ($this->hasService('foobaz'))
    {
      $instance->setBar($this->getService('foobaz', Container::NULL_ON_INVALID_REFERENCE));
    }

    return $this->shared['method_call1'] = $instance;
  }

  /**
   * Gets the alias_for_foo service alias.
   *
   * @return FooClass An instance of the foo service
   */
  protected function getAliasForFooService()
  {
    return $this->getService('foo');
  }

  /**
   * Gets the default parameters.
   *
   * @return array An array of the default parameters
   */
  protected function getDefaultParameters()
  {
    return array(
      'baz_class' => 'BazClass',
      'foo_class' => 'FooClass',
      'foo' => 'bar',
      'foo_bar' => new Reference('foo_bar'),
    );
  }
}
