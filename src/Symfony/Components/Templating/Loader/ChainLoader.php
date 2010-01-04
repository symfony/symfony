<?php

namespace Symfony\Components\Templating\Loader;

use Symfony\Components\Templating\Storage;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ChainLoader is a loader that calls other loaders to load templates.
 *
 * @package    symfony
 * @subpackage templating
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class ChainLoader extends Loader
{
  protected
    $loaders = array();

  /**
   * Constructor.
   *
   * @param array $loaders    An array of loader instances
   */
  public function __construct(array $loaders = array())
  {
    foreach ($loaders as $loader)
    {
      $this->addLoader($loader);
    }
  }

  /**
   * Adds a loader instance.
   *
   * @param Loader $loader A Loader instance
   */
  public function addLoader(Loader $loader)
  {
    $this->loaders[] = $loader;
  }

  /**
   * Loads a template.
   *
   * @param string $template The logical template name
   * @param string $renderer The renderer to use
   *
   * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
   */
  public function load($template, $renderer = 'php')
  {
    foreach ($this->loaders as $loader)
    {
      if (false !== $ret = $loader->load($template, $renderer))
      {
        return $ret;
      }
    }

    return false;
  }
}
