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
 * Tests whether a value satisfies a constraint.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeConstraintInterface.php 23701 2009-11-08 21:23:40Z bschussek $
 */
interface LimeConstraintInterface
{
  /**
   * Evaluates the constraint for the given value.
   *
   * If the evaluation fails, a LimeConstraintException with details about the
   * error is thrown.
   *
   * @param  mixed $value
   * @throws LimeConstraintException  If the evaluation fails
   */
  public function evaluate($value);
}