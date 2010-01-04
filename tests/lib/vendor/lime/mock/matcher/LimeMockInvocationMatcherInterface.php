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
 * Verifies whether some criteria has been fulfilled for a method invocation.
 *
 * You can configure a number of matchers for a method invocation in class
 * LimeExpectedInvocation. If a method is invoked and the method
 * signature and parameters match the expected invocation, the method call
 * is passed to the invoke() method of all associated matchers. This method
 * decides whether the invocation is valid and throws a
 * LimeMockInvocationMatcherException if it is not.
 *
 * When the mock object is verified, the method isSatisfied() is queried on all
 * matchers of every method. If all matchers are satisfied, the invocation
 * expectation is considered to be fulfilled.
 *
 * The method isInvokable() returns whether the matcher accepts any more
 * invocations. For example, if a matcher only accepts 3 invocation and throws
 * exceptions after that, isInvokable() should return false as soon as these
 * three invocations have been made.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationMatcherInterface.php 23701 2009-11-08 21:23:40Z bschussek $
 */
interface LimeMockInvocationMatcherInterface
{
  /**
   * Notifies the matcher of a given method invocation.
   *
   * If the matcher decides that the method should not have been invoked, it
   * must throw an exception in this method.
   *
   * @param  LimeMockInvocation $invocation      The method invocation
   * @throws LimeMockInvocationMatcherException  If the invocation was not valid
   */
  public function invoke(LimeMockInvocation $invocation);

  /**
   * Returns whether the matcher accepts any more invokations.
   *
   * @return boolean
   */
  public function isInvokable();

  /**
   * Returns whether the matcher's criteria is fulfilled.
   *
   * The matcher's criteria could be, for instance, that a method must be
   * invoked at least 3 times. As soon as this is the case, isSatisfied()
   * returns TRUE.
   *
   * @return boolean
   */
  public function isSatisfied();

  /**
   * The message describing the purpose of the matcher that is appended to
   * the method name in the test output.
   *
   * If this message returns "with any parameters", the resulting output is
   * "doSomething(x) was called with any parameters".
   *
   * @return string
   */
  public function getMessage();
}