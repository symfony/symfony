<?php

namespace Symfony\Components\Templating\Renderer;

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
 * Renderer is the base class for all template renderer.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Renderer implements RendererInterface
{
  protected $engine;

  /**
   * Sets the template engine associated with this renderer.
   *
   * @param Engine $engine A Engine instance
   */
  public function setEngine(Engine $engine)
  {
    $this->engine = $engine;
  }
}
