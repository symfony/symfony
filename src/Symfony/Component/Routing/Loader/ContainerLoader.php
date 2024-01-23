<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Psr\Container\ContainerInterface;

/**
 * A route loader that executes a service from a PSR-11 container to load the routes.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ContainerLoader extends ObjectLoader
{
    private $container;

    public function __construct(ContainerInterface $container, ?string $env = null)
    {
        $this->container = $container;
        parent::__construct($env);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, ?string $type = null)
    {
        return 'service' === $type && \is_string($resource);
    }

    /**
     * {@inheritdoc}
     */
    protected function getObject(string $id)
    {
        return $this->container->get($id);
    }
}
