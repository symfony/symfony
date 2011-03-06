<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ExtensionInterface is the interface implemented by container extension classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ExtensionInterface
{
    /**
     * Loads the current extension.
     *
     * @param array            $configs   An array of value array for all calls to load the current extension
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    function load(array $configs, ContainerBuilder $container);

    /**
     * Returns the name used in YAML files.
     *
     * @return string The extension name
     */
    function getName();

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string|Boolean The XML namespace or false if XML is not supported
     */
    function getNamespace();

    /**
     * Returns the base path for the XSD files.
     *
     * @return string|Boolean The XSD base path or false if schema validation is not supported
     */
    function getXsdValidationBasePath();
}
