<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

/**
 * Bundle value object.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
final class BundleVO
{
    private $name;
    private $namespace;
    private $className;
    private $path;
    private $parent;

    /**
     * Constructor.
     *
     * @param string        $name
     * @param string        $namespace
     * @param string        $className
     * @param string        $path
     * @param BundleVO|null $parent
     */
    public function __construct($name, $namespace, $className, $path, BundleVO $parent = null)
    {
        $this->name = $name;
        $this->className = $className;
        $this->path = $path;
        $this->parent = $parent;
    }

    /**
     * Get bundle name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get bundle namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get bundle class name.
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Get bundle path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get parent bundle, if any.
     *
     * @return BundleVO|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->className;
    }
}
