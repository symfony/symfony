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
 * Base class for all constraints.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeConstraint.php 23701 2009-11-08 21:23:40Z bschussek $
 */
abstract class LimeConstraint implements LimeConstraintInterface
{
  protected
    $expected     = null;

  /**
   * Constructor.
   *
   * @param $expected  The value against which the constraint should be tested
   */
  public function __construct($expected)
  {
    $this->expected = $expected;
  }
}