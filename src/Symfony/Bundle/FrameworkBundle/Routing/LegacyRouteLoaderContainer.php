<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Psr\Container\ContainerInterface;

/**
 * @internal to be removed in Symfony 5.0
 */
class LegacyRouteLoaderContainer implements ContainerInterface
{
    private $container;
    private $serviceLocator;

    public function __construct(ContainerInterface $container, ContainerInterface $serviceLocator)
    {
        $this->container = $container;
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function get($id)
    {
        if ($this->serviceLocator->has($id)) {
            return $this->serviceLocator->get($id);
        }

        @trigger_error(sprintf('Registering the service route loader "%s" without tagging it with the "routing.route_loader" tag is deprecated since Symfony 4.4 and will be required in Symfony 5.0.', $id), \E_USER_DEPRECATED);

        return $this->container->get($id);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function has($id)
    {
        return $this->serviceLocator->has($id) || $this->container->has($id);
    }
}
