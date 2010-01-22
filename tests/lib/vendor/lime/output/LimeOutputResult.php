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
 * Collects and interprets the input of a LimeOutput... instance.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeOutputResult.php 25932 2009-12-27 19:55:32Z bschussek $
 */
class LimeOutputResult
{
  private
    $nbExpected     = null,
    $nbActual       = 0,
    $nbPassed       = 0,
    $failures       = array(),
    $errors         = array(),
    $warnings       = array(),
    $todos          = array();

  /**
   * Adds the given amount of tests to the test plan.
   *
   * @param integer $plan
   */
  public function addPlan($plan)
  {
    $this->nbExpected += $plan;
  }

  /**
   * Adds a passed test.
   */
  public function addPassed()
  {
    $this->nbActual++;
    $this->nbPassed++;
  }

  /**
   * Adds a failed test.
   *
   * @param array $failure  The test failure. An array with the failure message,
   *                        the script, the line in the script and optionally
   *                        the specific error.
   */
  public function addFailure(array $failure)
  {
    $this->nbActual++;
    $this->failures[] = $failure;
  }

  /**
   * Adds a skipped test.
   */
  public function addSkipped()
  {
    $this->nbActual++;
    $this->nbPassed++;
  }

  /**
   * Adds a todo.
   *
   * @param string $text  The todo message.
   */
  public function addTodo($text)
  {
    $this->nbActual++;
    $this->todos[] = $text;
  }

  /**
   * Adds a test error.
   *
   * @param LimeError $error  The error.
   */
  public function addError(LimeError $error)
  {
    $this->errors[] = $error;
  }

  /**
   * Adds a test warning.
   *
   * @param array $warning  An array with the warning message, the path of the
   *                        test script and the line of the test script where
   *                        the warning occurred.
   */
  public function addWarning(array $warning)
  {
    $this->warnings[] = $warning;
  }

  /**
   * Returns the actual number of tests.
   *
   * @return integer
   */
  public function getNbActual()
  {
    return $this->nbActual;
  }

  /**
   * Returns the expected number of tests.
   *
   * @return integer
   */
  public function getNbExpected()
  {
    return is_null($this->nbExpected) ? $this->nbActual : $this->nbExpected;
  }

  /**
   * Returns the number of passed tests.
   *
   * @return integer
   */
  public function getNbPassed()
  {
    return $this->nbPassed;
  }

  /**
   * Returns the number of failed tests.
   *
   * @return integer
   */
  public function getNbFailures()
  {
    return count($this->failures);
  }

  /**
   * Returns the test failures.
   *
   * @return array
   */
  public function getFailures()
  {
    return $this->failures;
  }

  /**
   * Returns whether the test has any failures.
   *
   * @return boolean
   */
  public function hasFailures()
  {
    return $this->getNbFailures() > 0;
  }

  /**
   * Returns the number of test errors.
   *
   * @return integer
   */
  public function getNbErrors()
  {
    return count($this->errors);
  }

  /**
   * Returns the test errors.
   *
   * @return array
   */
  public function getErrors()
  {
    return $this->errors;
  }

  /**
   * Returns whether the test has any errors.
   *
   * @return boolean
   */
  public function hasErrors()
  {
    return $this->getNbErrors() > 0;
  }

  /**
   * Returns the number of test warnings.
   *
   * @return integer
   */
  public function getNbWarnings()
  {
    return count($this->warnings);
  }

  /**
   * Returns the test warnings.
   *
   * @return array
   */
  public function getWarnings()
  {
    return $this->warnings;
  }

  /**
   * Returns whether the test has any warnings.
   *
   * @return boolean
   */
  public function hasWarnings()
  {
    return $this->getNbWarnings() > 0;
  }

  /**
   * Returns the number of todos.
   *
   * @return integer
   */
  public function getNbTodos()
  {
    return count($this->todos);
  }

  /**
   * Returns the todos.
   *
   * @return integer
   */
  public function getTodos()
  {
    return $this->todos;
  }

  /**
   * Returns whether the test has any todos.
   *
   * @return boolean
   */
  public function hasTodos()
  {
    return $this->getNbTodos() > 0;
  }

  /**
   * Returns whether not all expected tests have been executed.
   *
   * @return boolean
   */
  public function isIncomplete()
  {
    return $this->nbExpected > 0 && $this->nbActual != $this->nbExpected;
  }

  /**
   * Returns whether the test has failed.
   *
   * A test is considered failed, if any test case failed, any error occurred
   * or the test is incomplete, i.e. not all expected tests have been executed.
   *
   * @return boolean
   */
  public function isFailed()
  {
    return $this->hasErrors() || $this->hasFailures() || $this->isIncomplete();
  }
}