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

/**
 * Definition that is aware of its service.
 *
 * The definition and service must remain in sync, in a way the created service object from definition is interchangeable with the aware service object.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ServiceAwareDefinition extends Definition
{
    private $service;

    /**
     * Sets the service this definition is aware of.
     *
     * @param object $service The service object tight to this definition
     *
     * @return ServiceAwareDefinition The current instance
     */
    public function setService($object)
    {
        $this->service = $object;

        return $this;
    }

    /**
     * Gets the aware service.
     *
     * @return object
     *
     * @throws \DomainException If the definition is not aware of a service object or the service object is invalid.
     */
    public function getService()
    {
        if (null === $this->service) {
            throw new \DomainException('A service aware definition must have a service object.');
        }
        $class = $this->getClass();
        if (null !== $class && !$this->service instanceof $class) {
            throw new \DomainException('The service object must be an instance of "'.$class.'", "'.get_class($this->service).'" given.');
        }

        return $this->service;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \BadMethodCallException When trying to prototype this definition
     */
    public function setShared($shared)
    {
        if (!$shared) {
            throw new \BadMethodCallException('A service aware definition must always be shared.');
        }

        return parent::setShared($shared);
    }
}
