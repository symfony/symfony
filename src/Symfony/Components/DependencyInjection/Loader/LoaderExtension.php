<?php

namespace Symfony\Components\DependencyInjection\Loader;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderExtension is a helper class that helps organize extensions better.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
abstract class LoaderExtension implements LoaderExtensionInterface
{
  /**
   * Loads a specific configuration.
   *
   * @param string The tag name
   * @param array  An array of configuration values
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function load($tag, array $config)
  {
    if (!method_exists($this, $method = $tag.'Load'))
    {
      throw new \InvalidArgumentException(sprintf('The tag "%s" is not defined in the "%s" extension.', $tag, $this->getNamespace()));
    }

    return $this->$method($config);
  }
}
