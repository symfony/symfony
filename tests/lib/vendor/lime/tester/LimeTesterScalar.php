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

class LimeTesterScalar extends LimeTester
{
  protected
    $type = 'scalar';

  public function __construct($value)
  {
    $this->type = gettype($value);

    parent::__construct($value);
  }

  public function __toString()
  {
    return var_export($this->value, true);
  }

  private function equals(LimeTesterInterface $other)
  {
    $exp1 = $this->value;
    $exp2 = $other->value;

    if (is_scalar($exp2) || is_null($exp2))
    {
      // always compare as strings to avoid strange behaviour
      // otherwise 0 == 'Foobar'
      if (is_string($exp1) || is_string($exp2))
      {
        $exp1 = (string)$exp1;
        $exp2 = (string)$exp2;
      }

      return $exp1 == $exp2;
    }
    else
    {
      return false;
    }
  }

  public function is(LimeTesterInterface $expected)
  {
    if (!$this->equals($expected))
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function same(LimeTesterInterface $expected)
  {
    if ($this->value !== $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isnt(LimeTesterInterface $expected)
  {
    if ($this->equals($expected))
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    if ($this->value === $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function greaterThan(LimeTesterInterface $expected)
  {
    if ($this->value <= $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function greaterThanEqual(LimeTesterInterface $expected)
  {
    if ($this->value < $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function lessThanEqual(LimeTesterInterface $expected)
  {
    if ($this->value > $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function lessThan(LimeTesterInterface $expected)
  {
    if ($this->value >= $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }
}