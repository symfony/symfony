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

class LimeOutputProxy implements LimeOutputInterface
{
  private
    $output       = null,
    $result       = null;

  public function __construct(LimeOutputInterface $output = null)
  {
    $this->output = is_null($output) ? new LimeOutputNone() : $output;
    $this->result = new LimeOutputResult();
  }

  public function supportsThreading()
  {
    return $this->output->supportsThreading();
  }

  public function getResult()
  {
    return $this->result;
  }

  /**
   * For BC with lime_harness.
   *
   * @deprecated
   * @return array
   */
  public function getFailedFiles()
  {
    return $this->failedFiles;
  }

  public function focus($file)
  {
    $this->output->focus($file);
  }

  public function close()
  {
    $this->output->close();
  }

  public function plan($amount)
  {
    $this->result->addPlan($amount);
    $this->output->plan($amount);
  }

  public function pass($message, $file, $line)
  {
    $this->result->addPassed();
    $this->output->pass($message, $file, $line);
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->result->addFailure(array($message, $file, $line, $error));
    $this->failedFiles[] = $file;
    $this->output->fail($message, $file, $line, $error);
  }

  public function skip($message, $file, $line)
  {
    $this->result->addSkipped();
    $this->output->skip($message, $file, $line);
  }

  public function todo($message, $file, $line)
  {
    $this->result->addTodo($message);
    $this->output->todo($message, $file, $line);
  }

  public function warning($message, $file, $line)
  {
    $this->result->addWarning(array($message, $file, $line));
    $this->failedFiles[] = $file;
    $this->output->warning($message, $file, $line);
  }

  public function error(LimeError $error)
  {
    $this->result->addError($error);
    $this->failedFiles[] = $error->getFile();
    $this->output->error($error);
  }

  public function comment($message)
  {
    $this->output->comment($message);
  }

  public function flush()
  {
    $this->output->flush();
  }
}