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

abstract class LimeTester implements LimeTesterInterface
{
  protected static
    $factory = null;

  protected
    $value = null,
    $type  = null;

  public static function create($value)
  {
    return self::getFactory()->create($value);
  }

  public static function register($type, $tester)
  {
    return self::getFactory()->register($type, $tester);
  }

  public static function unregister($type)
  {
    return self::getFactory()->unregister($type);
  }

  private static function getFactory()
  {
    if (is_null(self::$factory))
    {
      self::$factory = new LimeTesterFactory();
    }

    return self::$factory;
  }

  public function __construct($value)
  {
    $this->value = $value;
  }

  public function is(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function isnt(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function same(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function isntSame(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function like(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function unlike(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function greaterThan(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function greaterThanEqual(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function lessThan(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function lessThanEqual(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function contains(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }

  public function containsNot(LimeTesterInterface $expected)
  {
    throw new LimeAssertionFailedException($this, $expected);
  }
}