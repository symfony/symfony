<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\Asset\Exception\LogicException;

/**
 * Helps manage asset URLs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Kris Wallsmith <kris@symfony.com>
 */
class Packages
{
    private $defaultPackage;
    private $packages = array();

    /**
     * @param PackageInterface   $defaultPackage The default package
     * @param PackageInterface[] $packages       Additional packages indexed by name
     */
    public function __construct(PackageInterface $defaultPackage = null, array $packages = array())
    {
        $this->defaultPackage = $defaultPackage;

        foreach ($packages as $name => $package) {
            $this->addPackage($name, $package);
        }
    }

    /**
     * Sets the default package.
     *
     * @param PackageInterface $defaultPackage The default package
     */
    public function setDefaultPackage(PackageInterface $defaultPackage)
    {
        $this->defaultPackage = $defaultPackage;
    }

    /**
     * Adds a  package.
     *
     * @param string           $name    The package name
     * @param PackageInterface $package The package
     */
    public function addPackage($name, PackageInterface $package)
    {
        $this->packages[$name] = $package;
    }

    /**
     * Returns an asset package.
     *
     * @param string $name The name of the package or null for the default package
     *
     * @return PackageInterface An asset package
     *
     * @throws InvalidArgumentException If there is no package by that name
     * @throws LogicException If no default package is defined
     */
    public function getPackage($name = null)
    {
        if (null === $name) {
            if (null === $this->defaultPackage) {
                throw new LogicException('There is no default asset package, configure one first.');
            }

            return $this->defaultPackage;
        }

        if (!isset($this->packages[$name])) {
            throw new InvalidArgumentException(sprintf('There is no "%s" asset package.', $name));
        }

        return $this->packages[$name];
    }

    /**
     * Gets the version to add to public URL.
     *
     * @param string $path        A public path
     * @param string $packageName A package name
     *
     * @return string The current version
     */
    public function getVersion($path, $packageName = null)
    {
        return $this->getPackage($packageName)->getVersion($path);
    }

    /**
     * Returns the public path.
     *
     * Absolute paths (i.e. http://...) are returned unmodified.
     *
     * @param string $path        A public path
     * @param string $packageName The name of the asset package to use
     *
     * @return string A public path which takes into account the base path and URL path
     */
    public function getUrl($path, $packageName = null)
    {
        return $this->getPackage($packageName)->getUrl($path);
    }
}
