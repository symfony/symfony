<?php

namespace Symfony\Components\Templating\Storage;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Storage is the base class for all storage classes.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Storage
{
  protected $renderer;
  protected $template;

  /**
   * Constructor.
   *
   * @param string $template The template name
   */
  public function __construct($template, $renderer = null)
  {
    $this->template = $template;
    $this->renderer = $renderer;
  }

  /**
   * Returns the object string representation.
   *
   * @return string The template name
   */
  public function __toString()
  {
    return (string) $this->template;
  }

  abstract public function getContent();

  /**
   * Gets the renderer.
   *
   * @return string|null The renderer name or null if no renderer is stored for this template
   */
  public function getRenderer()
  {
    return $this->renderer;
  }
}
