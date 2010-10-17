<?php

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExtensionInterface is the interface implemented by container extension classes.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ExtensionInterface
{
    /**
     * Loads a specific configuration.
     *
     * @param string  $tag           The tag name
     * @param array   $config        An array of configuration values
     * @param ContainerBuilder $configuration A ContainerBuilder instance
     *
     * @return ContainerBuilder A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    function load($tag, array $config, ContainerBuilder $configuration);

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    function getNamespace();

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    function getXsdValidationBasePath();

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    function getAlias();
}
