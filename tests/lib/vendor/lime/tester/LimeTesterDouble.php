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

class LimeTesterDouble extends LimeTesterInteger
{
  const
    EPSILON = 0.0000000001;

  protected
    $type = 'double';

  public function __construct($value)
  {
    parent::__construct((double)$value);
  }

  public function __toString()
  {
    if ($this->value == round($this->value))
    {
      return sprintf('%.1f', $this->value);
    }
    else
    {
      return (string)$this->value;
    }
  }

  public function is(LimeTesterInterface $expected)
  {
    if (is_infinite($this->value) && is_infinite($expected->value))
    {
      return;
    }

    if (abs($this->value - $expected->value) >= self::EPSILON)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isnt(LimeTesterInterface $expected)
  {
    if ((is_infinite($this->value) && is_infinite($expected->value)) || abs($this->value - $expected->value) < self::EPSILON)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function same(LimeTesterInterface $expected)
  {
    $this->is($expected);

    if (gettype($this->value) != gettype($expected->value))
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    try
    {
      $this->is($expected);
    }
    catch (LimeAssertionFailedException $e)
    {
      if (gettype($this->value) == gettype($expected->value))
      {
        throw $e;
      }
    }
  }
}