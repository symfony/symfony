<?php

namespace Symfony\Components\Templating\Loader;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * CompilableLoaderInterface is the interface a template loader must implement
 * if the templates are compilable.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface CompilableLoaderInterface
{
  /**
   * Compiles a template.
   *
   * @param string $template The template to compile
   *
   * @return string The compiled template
   *
   * @throws \Exception if the template is not compilable
   */
  public function compile($template);
}
