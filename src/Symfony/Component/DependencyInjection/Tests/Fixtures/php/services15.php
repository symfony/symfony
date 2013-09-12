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
            'depends_on_synchronized' => 'getDependsonsynchronizedService',
            'synchronized_service' => 'getSynchronizedserviceService',
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
        $this->services['depends_on_synchronized'] = $instance = new \FooClass();

        $instance->setBar($this->get('synchronized_service', ContainerInterface::NULL_ON_INVALID_REFERENCE));

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
            throw new InactiveScopeException('synchronized_service', 'request');
        }

        $this->services['synchronized_service'] = $this->scopedServices['request']['synchronized_service'] = $instance = new \BarClass();

        $this->synchronizeSynchronizedserviceService();

        return $instance;
    }

    /**
     * Updates the 'synchronizedservice' service.
     */
    protected function synchronizeSynchronizedserviceService()
    {
        if ($this->initialized('depends_on_synchronized')) {
            $this->get('depends_on_synchronized')->setBar($this->get('synchronized_service', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }
}
