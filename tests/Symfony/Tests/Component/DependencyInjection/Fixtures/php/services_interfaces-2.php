<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * ProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(new ParameterBag($this->getDefaultParameters()));
    }

    /**
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return Object An instance returned by barFactory::createBarClass().
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = $this->get('barFactory')->createBarClass();


        $this->applyInterfaceInjectors($instance);

        return $instance;
    }

    /**
     * Gets the 'barfactory' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return BarClassFactory A BarClassFactory instance.
     */
    protected function getBarfactoryService()
    {
        $this->services['barfactory'] = $instance = new \BarClassFactory();


        $this->applyInterfaceInjectors($instance);

        return $instance;
    }

    /**
     * Applies all known interface injection calls
     *
     * @param Object $instance
     */
    protected function applyInterfaceInjectors($instance)
    {
        if ($instance instanceof \BarClass) {
            $instance->setFoo('someValue');
        }
    }
}
