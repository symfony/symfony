<?php

namespace Symfony\Component\DependencyInjection\Loader;

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
 * LoaderInterface is the interface implemented by all loader classes.
 *
 * $loader = new XXXLoader();
 * $config = $loader->load('resource_name');
 *
 * $container = new ContainerBuilder();
 * $container->merge($config);
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoaderInterface
{
    /**
     * Loads a resource.
     *
     * A resource can be anything that can be converted to a
     * ContainerBuilder instance.
     *
     * Some loaders support an array of resources as an argument to the
     * constructor.
     *
     * If multiple resources are loaded, the services and parameters are merged.
     *
     * Remember that services and parameters are simple key/pair stores.
     *
     * When overriding a value, the old one is totally replaced, even if it is
     * a "complex" value (an array for instance):
     *
     * <pre>
     *   file1.xml
     *   <parameter key="complex" type="collection">
     *     <parameter>true</parameter>
     *     <parameter>false</parameter>
     *   </parameter>
     *
     *   file2.xml
     *   <parameter key="complex">foo</parameter>
     * </pre>
     *
     * If you load file1.xml and file2.xml in this order, the value of complex
     * will be "foo".
     *
     * @param mixed $resource The resource
     */
    function load($resource);

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    function supports($resource);

    /**
     * Gets the loader resolver.
     *
     * @return LoaderResolver A LoaderResolver instance
     */
    function getResolver();

    /**
     * Sets the loader resolver.
     *
     * @param LoaderResolver $resolver A LoaderResolver instance
     */
    function setResolver(LoaderResolver $resolver);
}
