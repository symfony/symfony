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
 * Collects a number of LimeMockInvocationException objects.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationExceptionStack.php 24352 2009-11-24 19:49:42Z bschussek $
 */
class LimeMockInvocationExceptionStack extends LimeMockInvocationException
{
  protected
    $exceptions = array();

  /**
   * Ignores the parent constructor.
   */
  public function __construct() {}

  /**
   * Adds a new exception to the stack.
   *
   * The stack message is updated to contain the message of the exception.
   *
   * @param LimeMockInvocationException $exception
   */
  public function add(LimeMockInvocationException $exception)
  {
    $this->exceptions[] = $exception;

    if (count($this->exceptions) > 1)
    {
      $this->message = "One of the following errors occured:\n";

      for ($i = 1; $i <= count($this->exceptions); ++$i)
      {
        $message = LimeTools::indent(wordwrap($this->exceptions[$i-1]->getMessage(), 70), strlen($i)+2);

        $this->message .= sprintf("%s) %s\n", $i, trim($message));
      }
    }
    else
    {
      $this->message = $this->exceptions[0]->getMessage();
    }
  }

  /**
   * Returns TRUE when the stack contains no exceptions, FALSE otherwise.
   *
   * @return boolean
   */
  public function isEmpty()
  {
    return count($this->exceptions) == 0;
  }
}