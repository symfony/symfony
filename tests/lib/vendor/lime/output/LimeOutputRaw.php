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

class LimeOutputRaw implements LimeOutputInterface
{
  protected
    $initialized = false;

  protected function printCall($method, array $arguments = array())
  {
    foreach ($arguments as &$argument)
    {
      if (is_string($argument))
      {
        $argument = str_replace(array("\n", "\r"), array('\n', '\r'), $argument);
      }
    }

    if (!$this->initialized)
    {
      $this->initialized = true;
      print "\0raw\0";
    }

    print serialize(array($method, $arguments))."\n";
  }

  public function supportsThreading()
  {
    return true;
  }

  public function focus($file)
  {
    $this->printCall('focus', array($file));
  }

  public function close()
  {
    $this->printCall('close', array());
  }

  public function plan($amount)
  {
    $this->printCall('plan', array($amount));
  }

  public function pass($message, $file, $line)
  {
    $this->printCall('pass', array($message, $file, $line));
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->printCall('fail', array($message, $file, $line, $error));
  }

  public function skip($message, $file, $line)
  {
    $this->printCall('skip', array($message, $file, $line));
  }

  public function todo($message, $file, $line)
  {
    $this->printCall('todo', array($message, $file, $line));
  }

  public function warning($message, $file, $line)
  {
    $this->printCall('warning', array($message, $file, $line));
  }

  public function error(LimeError $error)
  {
    $this->printCall('error', array($error));
  }

  public function comment($message)
  {
    $this->printCall('comment', array($message));
  }

  public function flush()
  {
    $this->printCall('flush');
  }
}