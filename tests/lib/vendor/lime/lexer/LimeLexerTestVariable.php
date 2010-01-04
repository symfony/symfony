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
 * Extracts the first global variable containing a reference to an instance of
 * LimeTest or any subclass from a source file.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeLexerTestVariable.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeLexerTestVariable extends LimeLexer
{
  const
    NORMAL        = 0,
    VARIABLE      = 1,
    ASSIGNMENT    = 2,
    INSTANTIATION = 3;

  protected
    $lastVariable = null,
    $testVariable = null,
    $state        = self::NORMAL;

  /**
   * This method implements a turing machine for variable assignments.
   *
   * Once a variable name is caught, the object is set to state VARIABLE.
   * When the variable is succeeded by an assignment operator "=", the state
   * is set to ASSIGNMENT. If the assignment operator is succeeded by the
   * keyword "new", the state is set to INSTANTIATION. If the assignment
   * operator is succeeded by a class name that inherits class LimeTest,
   * processing is stopped and the variable name is returned. Otherwise,
   * the state is reset and processing continues.
   *
   * @see LimeLexer#process($text, $id)
   */
  protected function process($text, $id)
  {
    if ($id == T_VARIABLE && !$this->inFunction())
    {
      $this->lastVariable = $text;
      $this->state = self::VARIABLE;
    }
    else if ($text == '=' && $this->state == self::VARIABLE)
    {
      $this->state = self::ASSIGNMENT;
    }
    else if ($id == T_NEW && $this->state == self::ASSIGNMENT)
    {
      $this->state = self::INSTANTIATION;
    }
    else if ($id == T_STRING && $this->state == self::INSTANTIATION)
    {
      if (class_exists($text))
      {
        $class = new ReflectionClass($text);
        if ($text == 'LimeTest' || $class->isSubclassOf('LimeTest'))
        {
          $this->testVariable = $this->lastVariable;
          $this->stop();
        }
      }
      $this->state = self::NORMAL;
    }
    else if ($id != T_WHITESPACE)
    {
      $this->state = self::NORMAL;
    }
  }

  /**
   * (non-PHPdoc)
   * @see LimeLexer#getResult()
   */
  protected function getResult()
  {
    return $this->testVariable;
  }
}