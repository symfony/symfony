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
 * Requires a method to be invoked a specific number of times.
 *
 * The expected number of method invokations must be passed to the constructor.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationMatcherTimes.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMockInvocationMatcherInterface
 */
class LimeMockInvocationMatcherTimes implements LimeMockInvocationMatcherInterface
{
  private
    $expected = 0,
    $actual   = 0;

  /**
   * Constructor.
   *
   * @param integer $times  The expected number of method invokations0
   */
  public function __construct($times)
  {
    $this->expected = $times;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#invoke($invocation)
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    if ($this->actual < $this->expected)
    {
      $this->actual++;
    }
    else
    {
      if ($this->expected == 0)
      {
        throw new LimeMockInvocationMatcherException('should not be called');
      }
      else
      {
        $times = $this->getMessage();

        throw new LimeMockInvocationMatcherException(sprintf('should only be called %s', $times));
      }
    }
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isInvokable()
   */
  public function isInvokable()
  {
    return $this->actual < $this->expected;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isSatisfied()
   */
  public function isSatisfied()
  {
    return $this->actual >= $this->expected;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#getMessage()
   */
  public function getMessage()
  {
    return $this->expected == 1 ? 'once' : $this->expected.' times';
  }
}