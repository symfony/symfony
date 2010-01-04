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
 * The state of the mock during replay mode.
 *
 * In this state, invoked methods are verified automatically. If a method
 * was expected to be called, the configured return value of the method is
 * returned. If it was not expected, an exception is thrown instead.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockReplayState.php 23880 2009-11-14 10:14:34Z bschussek $
 */
class LimeMockReplayState implements LimeMockStateInterface
{
  protected
    $behaviour = null;

  /**
   * Constructor.
   *
   * @param LimeMockBehaviourInterface $behaviour  The behaviour on which this
   *                                               state operates.
   */
  public function __construct(LimeMockBehaviourInterface $behaviour)
  {
    $this->behaviour = $behaviour;
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#invoke($class, $method, $parameters)
   */
  public function invoke(LimeMockMethod $method, array $parameters = null)
  {
    return $this->behaviour->invoke(new LimeMockInvocation($method, is_null($parameters) ? array() : $parameters));
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#isInvokable($method)
   */
  public function isInvokable(LimeMockMethod $method)
  {
    return $this->behaviour->isInvokable($method);
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#setExpectNothing()
   */
  public function setExpectNothing()
  {
    throw new BadMethodCallException('setExpectNothing() must be called before replay()');
  }

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockStateInterface#verify()
   */
  public function verify()
  {
    return $this->behaviour->verify();
  }
}