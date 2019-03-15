<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Turns public and "container.reversible" services back to their ids.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ReverseContainer
{
    private $serviceContainer;
    private $reversibleLocator;
    private $tagName;
    private $getServiceId;

    public function __construct(Container $serviceContainer, ContainerInterface $reversibleLocator, string $tagName = 'container.reversible')
    {
        $this->serviceContainer = $serviceContainer;
        $this->reversibleLocator = $reversibleLocator;
        $this->tagName = $tagName;
        $this->getServiceId = \Closure::bind(function ($service): ?string {
            return array_search($service, $this->services, true) ?: array_search($service, $this->privates, true) ?: null;
        }, $serviceContainer, Container::class);
    }

    /**
     * Returns the id of the passed object when it exists as a service.
     *
     * To be reversible, services need to be either public or be tagged with "container.reversible".
     *
     * @param object $service
     */
    public function getId($service): ?string
    {
        if ($this->serviceContainer === $service) {
            return 'service_container';
        }

        if (null === $id = ($this->getServiceId)($service)) {
            return null;
        }

        if ($this->serviceContainer->has($id) || $this->reversibleLocator->has($id)) {
            return $id;
        }

        return null;
    }

    /**
     * @return object
     *
     * @throws ServiceNotFoundException When the service is not reversible
     */
    public function getService(string $id)
    {
        if ($this->serviceContainer->has($id)) {
            return $this->serviceContainer->get($id);
        }

        if ($this->reversibleLocator->has($id)) {
            return $this->reversibleLocator->get($id);
        }

        if (isset($this->serviceContainer->getRemovedIds()[$id])) {
            throw new ServiceNotFoundException($id, null, null, [], sprintf('The "%s" service is private and cannot be accessed by reference. You should either make it public, or tag it as "%s".', $id, $this->tagName));
        }

        // will throw a ServiceNotFoundException
        $this->serviceContainer->get($id);
    }
}
