<?php

namespace Symfony\Components\DependencyInjection\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Loader is the abstract class used by all built-in loaders.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Loader implements LoaderInterface
{
    static protected $extensions = array();

    /**
     * Registers an extension.
     *
     * @param LoaderExtensionInterface $extension An extension instance
     */
    static public function registerExtension(LoaderExtensionInterface $extension)
    {
        static::$extensions[$extension->getAlias()] = static::$extensions[$extension->getNamespace()] = $extension;
    }

    /**
     * Returns an extension by alias or namespace.
     *
     * @param string $name An alias or a namespace
     *
     * @return LoaderExtensionInterface An extension instance
     */
    static public function getExtension($name)
    {
        return isset(static::$extensions[$name]) ? static::$extensions[$name] : null;
    }
}
