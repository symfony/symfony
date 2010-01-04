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

class LimeAssertionFailedException extends Exception
{
  private
    $actual     = '',
    $expected   = '';

  public function __construct($actual, $expected)
  {
    parent::__construct(sprintf('Got: %s, Expected: %s', $actual, $expected));

    $this->actual = (string)$actual;
    $this->expected = (string)$expected;
  }

  public function getActual($indentation = 0)
  {
    if ($indentation > 0)
    {
      return $this->indent($this->actual, $indentation);
    }
    else
    {
      return $this->actual;
    }
  }

  public function getExpected($indentation = 0)
  {
    if ($indentation > 0)
    {
      return $this->indent($this->expected, $indentation);
    }
    else
    {
      return $this->expected;
    }
  }

  protected function indent($lines, $indentation = 2)
  {
    $lines = explode("\n", $lines);

    foreach ($lines as $key => $line)
    {
      $lines[$key] = str_repeat(' ', $indentation).$line;
    }

    return trim(implode("\n", $lines));
  }
}