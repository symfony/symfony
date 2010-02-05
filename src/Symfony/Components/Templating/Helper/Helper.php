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
 * Helper is the base class for all helper classes.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Helper implements HelperInterface
{
  protected $engine;

  /**
   * Sets the engine associated with this helper.
   *
   * @param Engine $engine A Engine instance
   */
  public function setEngine(Engine $engine = null)
  {
    $this->engine = $engine;
  }

  /**
   * Gets the engine associated with this helper.
   *
   * @return Engine A Engine instance
   */
  public function getEngine()
  {
    return $this->engine;
  }
}
