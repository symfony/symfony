<?php

/*
 * This file is part of the Lime test framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Represents a method on a class.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockMethod.php 23880 2009-11-14 10:14:34Z bschussek $
 */
class LimeMockMethod implements LimeMockMethodInterface
{
  protected
    $class          = null,
    $method         = null;

  /**
   * Constructor.
   *
   * @param string $class   The class name
   * @param string $method  The method name
   */
  public function __construct($class, $method)
  {
    $this->class = $class;
    $this->method = $method;
  }

  /**
   * Returns the class name.
   *
   * @return string
   */
  public function getClass()
  {
    return $this->class;
  }

  /**
   * Returns the method name.
   *
   * @return string
   */
  public function getMethod()
  {
    return $this->method;
  }
}