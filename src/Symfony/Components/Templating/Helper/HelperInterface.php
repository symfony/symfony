<?php

namespace Symfony\Components\Templating\Helper;

use Symfony\Components\Templating\Engine;

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
   * Sets the engine associated with this helper.
   *
   * @param Engine $engine A Engine instance
   */
  function setEngine(Engine $engine = null);

  /**
   * Gets the engine associated with this helper.
   *
   * @return Engine A Engine instance
   */
  function getEngine();
}
