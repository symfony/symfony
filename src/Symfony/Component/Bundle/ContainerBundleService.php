<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Bundle;

use Symfony\Component\DependencyInjection\Container;

/**
 * An extension of Bundle that adds support for Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerBundleService
{
    protected $removeSuffixRe = '/Bundle$/';
    protected $extensionNamespace = '\\DependencyInjection\\';
    protected $extensionSuffix = 'Extension';

    /**
     * Set basename regular expression
     *
     * Regular expression used to remove a suffix from the bundle's name.
     *
     * @param string $removeSuffixRe Regular expression
     *
     * @return ContainerBundleService
     */
    public function setBasenameRe($removeSuffixRe)
    {
        $this->removeSuffixRe = $removeSuffixRe;

        return $this;
    }

    /**
     * Set extension namespace
     *
     * Define the namespace (with leading and trailing '\\') in which
     * extension can be found. For example using '\\Extensions\\' as
     * the extension namespace for bundle named '\\My\\FancyBundle'
     * would result in '\\My\\Bundle\\Extensions\\' as the namespace
     * in which the extension would be expected.
     *
     * @param string $extensionNamespace Extension namespace
     *
     * @return ContainerBundleService
     */
    public function setExtensionNamespace($extensionNamespace)
    {
        $this->extensionNamespace = $extensionNamespace;

        return $this;
    }

    /**
     * Set extension suffix
     *
     * Define the class name suffix appended to the end of the bundle's
     * name. For example using '\\Extensions\\' as the extension namespace
     * for bundle named '\\My\\FancyBundle' would result in
     * '\\My\\FancyBundle\\Extensions\\FancyExtension' as the fully qualified
     * class name for the extension.
     *
     * @param string $extensionSuffix Extension suffix
     *
     * @return ContainerBundleService
     */
    public function setExtensionSuffix($extensionSuffix)
    {
        $this->extensionSuffix = $extensionSuffix;

        return $this;
    }

    /**
     * Returns the bundle's container extension.
     *
     * @param Bundle $bundle Bundle
     *
     * @return ExtensionInterface|null The container extension
     */
    public function getContainerExtension(Bundle $bundle)
    {
        if (!($bundle instanceof ContainerBundleInterface)) {
            return false;
        }

        $basename = preg_replace($this->removeSuffixRe, '', $bundle->getName());

        $class = $bundle->getNamespace().$this->extensionNamespace.$basename.$this->extensionSuffix;
        if (class_exists($class)) {
            $extension = new $class();

            // check naming convention
            $expectedAlias = Container::underscore($basename);
            if ($expectedAlias != $extension->getAlias()) {
                throw new \LogicException(sprintf(
                    'The extension alias for the default extension of a '.
                    'bundle must be the underscored version of the '.
                    'bundle name ("%s" instead of "%s")',
                    $expectedAlias, $extension->getAlias()
                ));
            }

            return $extension;
        } else {
            return false;
        }
    }
}
