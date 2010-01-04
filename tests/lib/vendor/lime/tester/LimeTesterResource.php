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

class LimeTesterResource extends LimeTester
{
  protected
    $type = 'resource';

  public function is(LimeTesterInterface $expected)
  {
    if ($this->value != $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isnt(LimeTesterInterface $expected)
  {
    if ($this->value == $expected->value)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function same(LimeTesterInterface $expected)
  {
    $this->is($expected);
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    $this->isnt($expected);
  }

  public function __toString()
  {
    return sprintf('resource(%s) of type (%s)', (integer)$this->value, get_resource_type($this->value));
  }
}