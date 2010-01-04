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
 * Requires a method to be called between X and Y times.
 *
 * The parameters X and Y are passed to the constructor. These values are
 * inclusive, that means that the matcher passes if the method is called
 * exactly X or Y times.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationMatcherBetween.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMockInvocationMatcherInterface
 */
class LimeMockInvocationMatcherBetween implements LimeMockInvocationMatcherInterface
{
  private
    $start  = 0,
    $end    = 0,
    $actual = 0;

  /**
   * Constructor.
   *
   * @param  integer $start  The lower limit of accepted invokation counts
   * @param  integer $end    The upper limit of accepted invokation counts
   */
  public function __construct($start, $end)
  {
    if ($start > $end)
    {
      $this->start = $end;
      $this->end = $start;
    }
    else
    {
      $this->start = $start;
      $this->end = $end;
    }
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#invoke($invocation)
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    if ($this->actual < $this->end)
    {
      $this->actual++;
    }
    else
    {
      throw new LimeMockInvocationMatcherException(sprintf('should only be called %s', $this->getMessage()));
    }
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isInvokable()
   */
  public function isInvokable()
  {
    return $this->actual < $this->end;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isSatisfied()
   */
  public function isSatisfied()
  {
    return $this->actual >= $this->start && $this->actual <= $this->end;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#getMessage()
   */
  public function getMessage()
  {
    return sprintf('between %s and % times', $this->start, $this->end);
  }
}