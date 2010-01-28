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
 */
class ChainLoader extends Loader
{
  protected $loaders;

  /**
   * Constructor.
   *
   * @param array $loaders    An array of loader instances
   */
  public function __construct(array $loaders = array())
  {
    $this->loaders = array();
    foreach ($loaders as $loader)
    {
      $this->addLoader($loader);
    }

    parent::__construct();
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
   * @param array  $options  An array of options
   *
   * @return Storage|Boolean false if the template cannot be loaded, a Storage instance otherwise
   */
  public function load($template, array $options = array())
  {
    foreach ($this->loaders as $loader)
    {
      if (false !== $ret = $loader->load($template, $options))
      {
        return $ret;
      }
    }

    return false;
  }
}
