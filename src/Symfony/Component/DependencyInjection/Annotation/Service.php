<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class Service
{
    private $shared;

    private $public;

    private $synthetic;

    private $abstract;

    private $lazy;

    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf('Unknown property "%s" on annotation "%s".', $key, get_class($this)));
            }
            $this->$method($value);
        }
    }

    public function isShared()
    {
        return $this->shared;
    }

    public function setShared($shared)
    {
        $this->shared = $shared;
    }

    public function isPublic()
    {
        return $this->public;
    }

    public function setPublic($public)
    {
        $this->public = $public;
    }

    public function isSynthetic()
    {
        return $this->synthetic;
    }

    public function setSynthetic($synthetic)
    {
        $this->synthetic = $synthetic;
    }

    public function isAbstract()
    {
        return $this->abstract;
    }

    public function setAbstract($abstract)
    {
        $this->abstract = $abstract;
    }

    public function isLazy()
    {
        return $this->lazy;
    }

    public function setLazy($lazy)
    {
        $this->lazy = $lazy;
    }
}
