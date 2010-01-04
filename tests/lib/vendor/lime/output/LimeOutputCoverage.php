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

class LimeOutputCoverage implements LimeOutputInterface
{
  public function supportsThreading()
  {
    return false;
  }

  public function focus($file)
  {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
  }

  public function flush()
  {
    echo serialize(xdebug_get_code_coverage());
  }

  public function close() {}

  public function plan($amount) {}

  public function pass($message, $file, $line) {}

  public function fail($message, $file, $line, $error = null) {}

  public function skip($message, $file, $line) {}

  public function todo($message, $file, $line) {}

  public function warning($message, $file, $line) {}

  public function error(LimeError $error) {}

  public function comment($message) {}
}