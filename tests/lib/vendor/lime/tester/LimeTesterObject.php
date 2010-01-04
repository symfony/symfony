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

class LimeTesterObject extends LimeTesterArray
{
  private static
    $equal    = array(),
    $unequal  = array();

  private
    $object   = null;

  public static function toArray($object)
  {
    if (!is_object($object))
    {
      throw new InvalidArgumentException('The argument must be an object');
    }

    $array = array();

    foreach ((array)$object as $key => $value)
    {
      // properties are transformed to keys in the following way:

      // private   $property => "\0Classname\0property"
      // protected $property => "\0*\0property"
      // public    $property => "property"

      if (preg_match('/^\0.+\0(.+)$/', $key, $matches))
      {
        $key = $matches[1];
      }

      $array[$key] = $value;
    }

    return $array;
  }

  public function __construct($object)
  {
    $this->object = $object;
    $this->type = get_class($object);

    parent::__construct(self::toArray($object));
  }

  protected function getType()
  {
    return 'object('.$this->type.')';
  }

  public function is(LimeTesterInterface $expected)
  {
    // allow comparison with strings if object implements __toString()
    if ($expected instanceof LimeTesterString && method_exists($this->object, '__toString'))
    {
      if ($expected->value != (string)$this->object)
      {
        throw new LimeAssertionFailedException($this, $expected);
      }
    }
    else
    {
      // don't compare twice to allow for cyclic dependencies
      if (in_array(array($this->value, $expected->value), self::$equal, true) || in_array(array($expected->value, $this->value), self::$equal, true))
      {
        return;
      }

      self::$equal[] = array($this->value, $expected->value);

      // don't compare objects if they are identical
      // this helps to avoid the error "maximum function nesting level reached"
      // CAUTION: this conditional clause is not tested
      if (!$expected instanceof self || $this->object !== $expected->object)
      {
        parent::is($expected);
      }
    }
  }

  public function isnt(LimeTesterInterface $expected)
  {
    // don't compare twice to allow for cyclic dependencies
    if (in_array(array($this->value, $expected->value), self::$unequal, true) || in_array(array($expected->value, $this->value), self::$unequal, true))
    {
      return;
    }

    self::$unequal[] = array($this->value, $expected->value);

    parent::isnt($expected);
  }

  public function same(LimeTesterInterface $expected)
  {
    if (!$expected instanceof self || $this->object !== $expected->object)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    if ($expected instanceof self && $this->object === $expected->object)
    {
      throw new LimeAssertionFailedException($this, $expected);
    }
  }
}