<?php

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Loader\LoaderResolver;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LazyLoader implements LoaderInterface
{
    protected $container;
    protected $service;

    public function __construct(ContainerInterface $container, $service)
    {
        $this->container = $container;
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource)
    {
        return $this->container->get($this->service)->load($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource)
    {
        return $this->container->get($this->service)->supports($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function getResolver()
    {
        return $this->container->get($this->service)->getResolver();
    }

    /**
     * {@inheritdoc}
     */
    public function setResolver(LoaderResolver $resolver)
    {
        $this->container->get($this->service)->setResolver($resolver);
    }
}
