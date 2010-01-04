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

class LimeExceptionExpectation
{
  private
    $exception  = null,
    $file       = null,
    $line       = null;

  public function __construct($exception, $file, $line)
  {
    $this->exception = $exception;
    $this->file = $file;
    $this->line = $line;
  }

  public function getException()
  {
    return $this->exception;
  }

  public function getFile()
  {
    return $this->file;
  }

  public function getLine()
  {
    return $this->line;
  }
}