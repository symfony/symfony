<?php

/**
 * A behaviour that allows methods to be invoked in the any order.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockUnorderedBehaviour.php 23864 2009-11-13 18:06:20Z bschussek $
 * @see        LimeMockBehaviourInterface
 */
class LimeMockUnorderedBehaviour extends LimeMockBehaviour
{
  /**
   * (non-PHPdoc)
   * @see mock/LimeMockBehaviour#invoke($invocation)
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    $exceptionStack = new LimeMockInvocationExceptionStack();

    foreach ($this->invocations as $invocationExpectation)
    {
      try
      {
        if ($invocationExpectation->matches($invocation))
        {
          return $invocationExpectation->invoke($invocation);
        }
      }
      catch (LimeMockInvocationException $e)
      {
        // make sure to test all expectations
        $exceptionStack->add($e);
      }
    }

    // no invocation matched and at least one exception was thrown
    if (!$exceptionStack->isEmpty())
    {
      throw $exceptionStack;
    }

    parent::invoke($invocation);
  }
}