<?php

use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

/**
 * ProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
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
   * Gets the default parameters.
   *
   * @return array An array of the default parameters
   */
  protected function getDefaultParameters()
  {
    return array(
      'foo' => 'bar',
      'bar' => 'foo is %foo bar',
      'values' => array(
        0 => true,
        1 => false,
        2 => NULL,
        3 => 0,
        4 => 1000.3,
        5 => 'true',
        6 => 'false',
        7 => 'null',
      ),
    );
  }
}
