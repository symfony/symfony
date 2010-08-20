<?php

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Loader\LoaderResolver as BaseLoaderResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Loader\LoaderInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This loader resolver automatically registers routing loaders from
 * the container.
 *
 * If also lazy-loads them.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LoaderResolver extends BaseLoaderResolver
{
    protected $services;
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param LoaderInterface[]  $loaders An array of loaders
     */
    public function __construct(ContainerInterface $container, array $loaders = array())
    {
        parent::__construct($loaders);

        $this->container = $container;
        foreach ($container->findTaggedServiceIds('routing.loader') as $id => $attributes) {
            $this->services[] = $id;
        }
    }

    /**
     * Returns a loader able to load the resource.
     *
     * @param mixed  $resource A resource
     *
     * @return LoaderInterface A LoaderInterface instance
     */
    public function resolve($resource)
    {
        if (count($this->services)) {
            while ($id = array_shift($this->services)) {
                $this->addLoader($this->container->get($id));
            }
        }

        return parent::resolve($resource);
    }
}
