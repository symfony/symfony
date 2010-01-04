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
 * HelperInterface is the interface all helpers must implement.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
interface HelperInterface
{
  /**
   * Returns the canonical name of this helper.
   *
   * @return string The canonical name
   */
  function getName();

  /**
   * Sets the helper set associated with this helper.
   *
   * @param HelperSet $helperSet A HelperSet instance
   */
  function setHelperSet(HelperSet $helperSet = null);

  /**
   * Gets the helper set associated with this helper.
   *
   * @return HelperSet A HelperSet instance
   */
  function getHelperSet();
}
