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
 * LoaderExtensionInterface is the interface implemented by loader extension classes.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
interface LoaderExtensionInterface
{
  /**
   * Loads a specific configuration.
   *
   * @param string The tag name
   * @param array  An array of configuration values
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function load($tag, array $config);

  /**
   * Returns the namespace to be used for this extension.
   *
   * @return string The namespace
   */
  public function getNamespace();
}
