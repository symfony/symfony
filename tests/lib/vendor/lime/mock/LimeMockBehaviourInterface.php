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
 * A behaviour specifies how the mock compares expected method invocations with
 * actual method invocations.
 *
 * The behaviour is fed with different invocation expectations by calling the
 * method expect(). Later, the method invoke() is called for all actual
 * invocations. The behaviour has to decide which expectations to compare
 * with incoming actual invocations. One behaviour implementation may, for
 * example, decide to accept invoked invocations only if they are called
 * in the same order as the invocation expectations.
 *
 * In the end, verify() can be called on the behaviour to verify whether all
 * expectations have been met.
 *
 * The behaviour should pass the matching of methods and method verification
 * to LimeMockInvocationExpectation.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockBehaviourInterface.php 23880 2009-11-14 10:14:34Z bschussek $
 */
interface LimeMockBehaviourInterface
{
  /**
   * Adds the following invocation expectation.
   *
   * @param LimeMockInvocationExpectation $invocation
   */
  public function expect(LimeMockInvocationExpectation $invocation);

  /**
   * Invokes the given method invocation.
   *
   * If the method invocation is not expected, this method should throw a
   * LimeMockInvocationException. Otherwise the call should be passed to
   * the method invoke() of LimeMockInvocationExpectation, its return value
   * should be returned.
   *
   * @param  LimeMockInvocation $invocation   The invoked method
   * @return mixed                            The return value of
   *                                          LimeMockInvocationExpectation#invoke()
   * @throws LimeMockInvocationException      If the method should not have been
   *                                          invoked
   */
  public function invoke(LimeMockInvocation $invocation);

  /**
   * Returns whether the given method is invokable.
   *
   * @param  LimeMockMethod $method  The method
   * @return boolean                 TRUE if the method is invokable
   */
  public function isInvokable(LimeMockMethod $method);

  /**
   * Verifies whether all expectations have been fulfilled.
   *
   * You should call LimeMockInvocationExpectation#verify() to implement this
   * method.
   */
  public function verify();

  /**
   * Configures the behaviour to expect no method to be invoked.
   */
  public function setExpectNothing();

  /**
   * Clears all invocation expectations in the behaviour.
   */
  public function reset();
}