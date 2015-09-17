<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Configuration loader that holds the ContainerBuilder inside.
 *
 * It can be useful to pass this to KernelInterface::registerContainerConfiguration()
 * instead of a normal Loader if that method will need the ContainerBuilder.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ContainerBuilderAwareLoader implements LoaderInterface
{
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    /**
     * @var LoaderInterface
     */
    private $resourceLoader;

    public function __construct(ContainerBuilder $builder, LoaderInterface $resourceLoader)
    {
        $this->containerBuilder = $builder;
        $this->resourceLoader = $resourceLoader;
    }

    /**
     * @return ContainerBuilder
     */
    public function getContainerBuilder()
    {
        return $this->containerBuilder;
    }

    /**
     * @return LoaderInterface
     */
    public function getResourceLoader()
    {
        return $this->resourceLoader;
    }

    /**
     * @see {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        return $this->resourceLoader->load($resource, $type);
    }

    /**
     * @see {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return $this->resourceLoader->supports($resource, $type);
    }

    /**
     * @see {@inheritdoc}
     */
    public function getResolver()
    {
        return $this->resourceLoader->getResolver();
    }

    /**
     * @see {@inheritdoc}
     */
    public function setResolver(LoaderResolverInterface $resolver)
    {
        return $this->resourceLoader->setResolver($resolver);
    }
}
