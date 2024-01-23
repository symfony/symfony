<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

use Symfony\Component\Config\Exception\LoaderLoadException;

/**
 * Loader is the abstract class used by all built-in loaders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Loader implements LoaderInterface
{
    protected $resolver;
    protected $env;

    public function __construct(?string $env = null)
    {
        $this->env = $env;
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->resolver;
    }

    /**
     * @return void
     */
    public function setResolver(LoaderResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Imports a resource.
     *
     * @return mixed
     */
    public function import(mixed $resource, ?string $type = null)
    {
        return $this->resolve($resource, $type)->load($resource, $type);
    }

    /**
     * Finds a loader able to load an imported resource.
     *
     * @throws LoaderLoadException If no loader is found
     */
    public function resolve(mixed $resource, ?string $type = null): LoaderInterface
    {
        if ($this->supports($resource, $type)) {
            return $this;
        }

        $loader = null === $this->resolver ? false : $this->resolver->resolve($resource, $type);

        if (false === $loader) {
            throw new LoaderLoadException($resource, null, 0, null, $type);
        }

        return $loader;
    }
}
