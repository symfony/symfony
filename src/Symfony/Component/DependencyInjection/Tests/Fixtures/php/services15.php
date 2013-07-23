<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * AnotherProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class AnotherProjectServiceContainer extends Container
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->services =
        $this->scopedServices =
        $this->scopeStacks = array();

        $this->set('service_container', $this);

        $this->scopes = array('request' => 'container');
        $this->scopeChildren = array('request' => array());
        $this->methodMap = array(
            'dependsonsynchronized' => 'getDependsonsynchronizedService',
            'synchronizedservice' => 'getSynchronizedserviceService',
        );

        $this->aliases = array();
    }

    /**
     * Gets the 'dependsonsynchronized' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return FooClass A FooClass instance.
     */
    protected function getDependsonsynchronizedService()
    {
        $this->services['dependsonsynchronized'] = $instance = new \FooClass();

        $instance->setBar($this->get('synchronizedservice', ContainerInterface::NULL_ON_INVALID_REFERENCE));

        return $instance;
    }

    /**
     * Gets the 'synchronizedservice' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return BarClass A BarClass instance.
     * 
     * @throws InactiveScopeException when the 'synchronizedservice' service is requested while the 'request' scope is not active
     */
    protected function getSynchronizedserviceService()
    {
        if (!isset($this->scopedServices['request'])) {
            throw new InactiveScopeException('synchronizedservice', 'request');
        }

        $this->services['synchronizedservice'] = $this->scopedServices['request']['synchronizedservice'] = $instance = new \BarClass();

        $this->synchronizeSynchronizedserviceService();

        return $instance;
    }

    /**
     * Updates the 'synchronizedservice' service.
     */
    protected function synchronizeSynchronizedserviceService()
    {
        if ($this->initialized('dependsonsynchronized')) {
            $this->get('dependsonsynchronized')->setBar($this->get('synchronizedservice', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }
}
