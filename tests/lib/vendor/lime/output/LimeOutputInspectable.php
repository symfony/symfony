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

class LimeOutputInspectable implements LimeOutputInterface
{
  private
    $output       = null,
    $planned      = 0,
    $passed       = 0,
    $failed       = 0,
    $skipped      = 0,
    $todos        = 0,
    $errors       = 0,
    $warnings     = 0,
    $failedFiles  = array();

  public function __construct(LimeOutputInterface $output = null)
  {
    $this->output = is_null($output) ? new LimeOutputNone() : $output;
  }

  public function supportsThreading()
  {
    return $this->output->supportsThreading();
  }

  public function getPlanned()
  {
    return $this->planned;
  }

  public function getPassed()
  {
    return $this->passed;
  }

  public function getFailed()
  {
    return $this->failed;
  }

  public function getSkipped()
  {
    return $this->skipped;
  }

  public function getTodos()
  {
    return $this->todos;
  }

  public function getErrors()
  {
    return $this->errors;
  }

  public function getWarnings()
  {
    return $this->warnings;
  }

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
    $this->planned += $amount;
    $this->output->plan($amount);
  }

  public function pass($message, $file, $line)
  {
    $this->passed++;
    $this->output->pass($message, $file, $line);
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->failed++;
    $this->failedFiles[] = $file;
    $this->output->fail($message, $file, $line, $error);
  }

  public function skip($message, $file, $line)
  {
    $this->skipped++;
    $this->output->skip($message, $file, $line);
  }

  public function todo($message, $file, $line)
  {
    $this->todos++;
    $this->output->todo($message, $file, $line);
  }

  public function warning($message, $file, $line)
  {
    $this->warnings++;
    $this->failedFiles[] = $file;
    $this->output->warning($message, $file, $line);
  }

  public function error(LimeError $error)
  {
    $this->errors++;
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