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
 * This interface is implemented by all mock objects.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInterface.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMock
 */
interface LimeMockInterface
{
  /**
   * Constructor.
   *
   * @param  string                     $class      The name of the mocked class
   * @param  LimeMockBehaviourInterface $behaviour  The behaviour for invocation
   *                                                comparisons.
   * @param  LimeOutputInterface        $output     The output for displaying
   *                                                the comparison results.
   */
  public function __construct($class, LimeMockBehaviourInterface $behaviour, LimeOutputInterface $output);

  /**
   * Invokes the given method.
   *
   * The mock reacts accordingly depending on whether it is in record or in
   * replay mode. In record mode, mocks return an instance of
   * LimeMockInvocationExpectation, which you can use to further configure
   * the expected invocation. In replay mode, the configured return value
   * is returned. If you configured the method to throw an exception, this
   * exception will be thrown here.
   *
   * @param  string $method       The method
   * @param  array  $parameters   The method parameters
   * @return LimeMockInvocationExpectation|mixed
   * @throws LimeMockException    If the method should not have been invoked
   * @throws Exception            If you configured the mock to throw an exception
   */
  public function __call($method, $parameters);

  /**
   * Switches the mock object into replay mode.
   *
   * @throws BadMethodCallException  If the object already is in replay mode
   */
  public function __lime_replay();

  /**
   * Resets all expected invocations in the mock and switches it into record
   * mode.
   *
   * @see LimeMockBehaviourInterface#reset()
   */
  public function __lime_reset();

  /**
   * Returns the object representing the current state of the mock.
   *
   * @return LimeMockStateInterface
   */
  public function __lime_getState();
}