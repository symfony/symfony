<?php

/*
 * This file is part of the Lime framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A behaviour that requires methods to be invoked in the same order as they
 * were expected.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockOrderedBehaviour.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMockBehaviourInterface
 */
class LimeMockOrderedBehaviour extends LimeMockBehaviour
{
  protected
    $cursor = 0;

  /**
   * (non-PHPdoc)
   * @see mock/LimeMockBehaviour#invoke($invocation)
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    if (array_key_exists($this->cursor, $this->invocations))
    {
      $invocationExpectation = $this->invocations[$this->cursor];

      if ($invocationExpectation->matches($invocation) && $invocationExpectation->isInvokable())
      {
        return $invocationExpectation->invoke($invocation);
      }
      else if ($invocationExpectation->isSatisfied())
      {
        $this->cursor++;

        return $this->invoke($invocation);
      }
    }

    parent::invoke($invocation);
  }
}