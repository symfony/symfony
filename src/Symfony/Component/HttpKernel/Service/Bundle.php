<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Service;

/**
 * The bundle service represents a bundle as a service throughout the ecosystem.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class Bundle
{
    private $name;
    private $namespace;
    private $className;
    private $path;
    private $parent;

    /**
     * Constructor.
     *
     * @param string      $name
     * @param string      $namespace
     * @param string      $className
     * @param string      $path
     * @param Bundle|null $parent
     */
    public function __construct($name, $namespace, $className, $path, Bundle $parent = null)
    {
        $this->name = $name;
        $this->className = $className;
        $this->path = $path;
        $this->parent = $parent;
    }

    final public function getName()
    {
        return $this->name;
    }

    final public function getNamespace()
    {
        return $this->namespace;
    }

    final public function getClassName()
    {
        return $this->className;
    }

    final public function getPath()
    {
        return $this->path;
    }

    final public function getParent()
    {
        return $this->parent;
    }
}
