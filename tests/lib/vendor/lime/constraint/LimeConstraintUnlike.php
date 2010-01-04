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

/**
 * Tests that a value is unlike another.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeConstraintUnlike.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeConstraintUnlike extends LimeConstraint
{
  /**
   * (non-PHPdoc)
   * @see constraint/LimeConstraintInterface#evaluate($value)
   */
  public function evaluate($value)
  {
    try
    {
      LimeTester::create($value)->unlike(LimeTester::create($this->expected));
    }
    catch (LimeAssertionFailedException $e)
    {
      throw new LimeConstraintException(sprintf("         %s\nmatches %s", $e->getActual(), $e->getExpected()));
    }
  }
}