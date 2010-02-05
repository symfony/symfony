<?php

namespace Symfony\Components\Templating\Renderer;

use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\FileStorage;
use Symfony\Components\Templating\Storage\StringStorage;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PhpRenderer is a renderer for PHP templates.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpRenderer extends Renderer
{
  /**
   * Evaluates a template.
   *
   * @param Storage $template   The template to render
   * @param array   $parameters An array of parameters to pass to the template
   *
   * @return string|false The evaluated template, or false if the renderer is unable to render the template
   */
  public function evaluate(Storage $template, array $parameters = array())
  {
    if ($template instanceof FileStorage)
    {
      extract($parameters);
      $view = $this->engine;
      ob_start();
      require $template;

      return ob_get_clean();
    }
    else if ($template instanceof StringStorage)
    {
      extract($parameters);
      $view = $this->engine;
      ob_start();
      eval('; ?>'.$template.'<?php ;');

      return ob_get_clean();
    }

    return false;
  }
}
