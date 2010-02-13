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
 */
interface LoaderExtensionInterface
{
  /**
   * Sets a configuration entry point for the given extension name.
   *
   * @param string The configuration extension name
   * @param mixed  A resource
   */
  public function setConfiguration($name, $resource);

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
   * Returns the namespace to be used for this extension (XML namespace).
   *
   * @return string The XML namespace
   */
  public function getNamespace();

  /**
   * Returns the base path for the XSD files.
   *
   * @return string The XSD base path
   */
  public function getXsdValidationBasePath();

  /**
   * Returns the recommanded alias to use in XML.
   *
   * This alias is also the mandatory prefix to use when using YAML.
   *
   * @return string The alias
   */
  public function getAlias();
}
