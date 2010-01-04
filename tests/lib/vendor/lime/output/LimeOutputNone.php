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

class LimeOutputNone implements LimeOutputInterface
{
  public function supportsThreading()
  {
    return true;
  }

  public function focus($file) {}

  public function close() {}

  public function plan($amount) {}

  public function pass($message, $file, $line) {}

  public function fail($message, $file, $line, $error = null) {}

  public function skip($message, $file, $line) {}

  public function todo($message, $file, $line) {}

  public function warning($message, $file, $line) {}

  public function error(LimeError $error) {}

  public function comment($message) {}

  public function flush() {}
}