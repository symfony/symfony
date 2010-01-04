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

interface LimeTesterInterface
{
  public function __toString();

  public function is(LimeTesterInterface $expected);

  public function isnt(LimeTesterInterface $expected);

  public function same(LimeTesterInterface $expected);

  public function isntSame(LimeTesterInterface $expected);

  public function like(LimeTesterInterface $expected);

  public function unlike(LimeTesterInterface $expected);

  public function greaterThan(LimeTesterInterface $expected);

  public function greaterThanEqual(LimeTesterInterface $expected);

  public function lessThan(LimeTesterInterface $expected);

  public function lessThanEqual(LimeTesterInterface $expected);

  public function contains(LimeTesterInterface $expected);

  public function containsNot(LimeTesterInterface $expected);
}