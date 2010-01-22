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

class LimeTesterArray extends LimeTester implements ArrayAccess, Iterator
{
  protected
    $type         = 'array';

  public function is(LimeTesterInterface $expected)
  {
    if (!$expected instanceof LimeTesterArray || $this->getType() !== $expected->getType())
    {
      throw new LimeAssertionFailedException($this, $expected);
    }

    $remaining = $this->value;

    foreach ($expected as $key => $value)
    {
      if (!isset($this[$key]))
      {
        throw new LimeAssertionFailedException($this, $expected->dumpExcerpt($key, $value));
      }

      try
      {
        $this[$key]->is($value);
      }
      catch (LimeAssertionFailedException $e)
      {
        throw new LimeAssertionFailedException($this->dumpExcerpt($key, $e->getActual()), $expected->dumpExcerpt($key, $e->getExpected()));
      }

      unset($remaining[$key]);
    }

    foreach ($remaining as $key => $value)
    {
      throw new LimeAssertionFailedException($this->dumpExcerpt($key, $value), $expected);
    }
  }

  public function isnt(LimeTesterInterface $expected)
  {
    if (!$expected instanceof LimeTesterArray || $this->getType() !== $expected->getType())
    {
      return;
    }

    foreach ($expected as $key => $value)
    {
      if (!isset($this[$key]))
      {
        return;
      }

      try
      {
        $this[$key]->isnt($value);
        return;
      }
      catch (LimeAssertionFailedException $e)
      {
      }
    }

    throw new LimeAssertionFailedException($this, $expected);
  }

  public function same(LimeTesterInterface $expected)
  {
    if (!$expected instanceof LimeTesterArray || $this->getType() !== $expected->getType())
    {
      throw new LimeAssertionFailedException($this, $expected);
    }

    for ($expected->rewind(), $this->rewind(); $expected->valid(); $expected->next(), $this->next())
    {
      if (!$this->valid())
      {
        throw new LimeAssertionFailedException($this, $expected->dumpExcerpt($expected->key(), $expected->current()));
      }

      if ($this->key() != $expected->key())
      {
        throw new LimeAssertionFailedException($this->dumpExcerpt(key($this->value), current($this->value)), $expected->dumpExcerpt($expected->key(), $expected->current()));
      }

      try
      {
        $this->current()->same($expected->current());
      }
      catch (LimeAssertionFailedException $e)
      {
        throw new LimeAssertionFailedException($this->dumpExcerpt($this->key(), $e->getActual()), $expected->dumpExcerpt($expected->key(), $e->getExpected()));
      }
    }

    if ($this->valid())
    {
      throw new LimeAssertionFailedException($this->dumpExcerpt($this->key(), $this->current()), $expected);
    }
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    if (!$expected instanceof LimeTesterArray || $this->getType() !== $expected->getType())
    {
      return;
    }

    for ($expected->rewind(), $this->rewind(); $expected->valid(); $expected->next(), $this->next())
    {
      if (!$this->valid() || $this->key() !== $expected->key())
      {
        return;
      }

      try
      {
        $this->current()->isntSame($expected->current());
      }
      catch (LimeAssertionFailedException $e)
      {
        throw new LimeAssertionFailedException($this->dumpExcerpt($this->key(), $e->getActual()), $expected->dumpExcerpt($expected->key(), $e->getExpected()));
      }
    }
  }

  public function contains(LimeTesterInterface $expected)
  {
    foreach ($this as $key => $value)
    {
      try
      {
        $value->is($expected);
        return;
      }
      catch (LimeAssertionFailedException $e)
      {
      }
    }

    throw new LimeAssertionFailedException($this->dumpAll(), $expected);
  }

  public function containsNot(LimeTesterInterface $expected)
  {
    foreach ($this as $key => $value)
    {
      $equal = true;

      try
      {
        $value->is($expected);
      }
      catch (LimeAssertionFailedException $e)
      {
        $equal = false;
      }

      if ($equal)
      {
        throw new LimeAssertionFailedException($this->dumpAll(), $expected);
      }
    }
  }

  public function __toString()
  {
    return $this->dumpExcerpt();
  }

  protected function getType()
  {
    return 'array';
  }

  protected function dumpAll()
  {
    $result = $this->getType().' (';

    if (!empty($this->value))
    {
      $result .= "\n";

      foreach ($this->value as $k => $v)
      {
        $result .= sprintf("  %s => %s,\n", var_export($k, true), $this->indent($v));
      }
    }

    $result .= ')';

    return $result;
  }

  protected function dumpExcerpt($key = null, $value = null)
  {
    $result = $this->getType().' (';

    if (!empty($this->value))
    {
      $truncated = false;
      $result .= "\n";

      foreach ($this->value as $k => $v)
      {
        if ((is_null($key) || $key !== $k) && !$truncated)
        {
          $result .= "  ...\n";
          $truncated = true;
        }
        else if ($k === $key)
        {
          $value = is_null($value) ? $v : $value;
          $result .= sprintf("  %s => %s,\n", var_export($k, true), $this->indent($value));
          $truncated = false;
        }
      }
    }

    $result .= ')';

    return $result;
  }

  protected function indent($lines)
  {
    $lines = explode("\n", $lines);

    foreach ($lines as $key => $line)
    {
      $lines[$key] = '  '.$line;
    }

    return trim(implode("\n", $lines));
  }

  public function offsetGet($key)
  {
    return LimeTester::create($this->value[$key]);
  }

  public function offsetExists($key)
  {
    return array_key_exists($key, $this->value);
  }

  public function offsetSet($key, $value)
  {
    throw new BadMethodCallException('This method is not supported');
  }

  public function offsetUnset($key)
  {
    throw new BadMethodCallException('This method is not supported');
  }

  public function current()
  {
    return $this[$this->key()];
  }

  public function key()
  {
    return key($this->value);
  }

  public function next()
  {
    next($this->value);
  }

  public function valid()
  {
    return $this->key() !== null;
  }

  public function rewind()
  {
    reset($this->value);
  }
}