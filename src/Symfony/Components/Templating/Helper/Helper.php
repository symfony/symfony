<?php

namespace Symfony\Components\Templating\Helper;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Helper is the base class for all helper classes.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class Helper implements HelperInterface
{
  protected
    $helperSet = null;

  /**
   * Sets the helper set associated with this helper.
   *
   * @param HelperSet $helperSet A HelperSet instance
   */
  public function setHelperSet(HelperSet $helperSet = null)
  {
    $this->helperSet = $helperSet;
  }

  /**
   * Gets the helper set associated with this helper.
   *
   * @return HelperSet A HelperSet instance
   */
  public function getHelperSet()
  {
    return $this->helperSet;
  }
}
