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
 * The state of the mock during record mode.
 *
 * During record mode, all methods that are called on the mock are turned into
 * invocation expectations. You may set modifiers on these expectations to
 * configure whether invocations should return values, throw exceptions etc.
 * in replay mode. See the description of LimeMockInvocationExpectation for
 * more information.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockRecordState.php 23880 2009-11-14 10:14:34Z bschussek $
 * @see        LimeMockInvocationExpectation
 */
class LimeMockRecordState implements LimeMockStateInterface
{
  protected
    $behaviour = null,
    $output = null;

  /**
   * Constructor.
   *
   * @param  LimeMockBehaviourInterface $behaviour  The behaviour on which this
   *                                                state operates
   * @param  LimeOutputInterface        $output     The output where failed and
   *                                                successful tests are written
   *                                                to.
   */
  public function __construct(LimeMockBehaviourInterface $behaviour, LimeOutputInterface $output)
  {
    $this->behaviour = $behaviour;
    $this->output = $output;
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#invoke($method, $parameters)
   */
  public function invoke(LimeMockMethod $method, array $parameters = null)
  {
    $invocation = new LimeMockInvocation($method, is_null($parameters) ? array() : $parameters);
    $invocation = new LimeMockInvocationExpectation($invocation, $this->output);

    if (is_null($parameters))
    {
      $invocation->withAnyParameters();
    }
    else
    {
      $invocation->withParameters($parameters);
    }

    $this->behaviour->expect($invocation);

    return $invocation;
  }

  /**
   * All method can be invoked during record mode.
   *
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#isInvokable($method)
   */
  public function isInvokable(LimeMockMethod $method)
  {
    return true;
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#setExpectNothing()
   */
  public function setExpectNothing()
  {
    return $this->behaviour->setExpectNothing();
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#verify()
   */
  public function verify()
  {
    throw new BadMethodCallException('replay() must be called before verify()');
  }
}