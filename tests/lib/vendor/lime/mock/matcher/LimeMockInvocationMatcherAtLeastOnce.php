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
 * Requires a method call to be invoked once or more.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationMatcherAtLeastOnce.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMockInvocationMatcherInterface
 */
class LimeMockInvocationMatcherAtLeastOnce implements LimeMockInvocationMatcherInterface
{
  private
    $actual   = 0;

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#invoke($invocation)
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    $this->actual++;

    return true;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isInvokable()
   */
  public function isInvokable()
  {
    return true;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#isSatisfied()
   */
  public function isSatisfied()
  {
    return $this->actual >= 1;
  }

  /**
   * (non-PHPdoc)
   * @see mock/matcher/LimeMockInvocationMatcherInterface#getMessage()
   */
  public function getMessage()
  {
    return 'at least once';
  }
}