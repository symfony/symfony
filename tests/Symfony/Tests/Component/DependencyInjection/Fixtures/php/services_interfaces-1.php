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
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return Object A %cla%o%ss% instance.
     */
    protected function getFooService()
    {
        $class = $this->getParameter('cla').'o'.$this->getParameter('ss');
        $this->services['foo'] = $instance = new $class();


        $this->applyInterfaceInjectors($instance);

        return $instance;
    }

    /**
     * Gets the default parameters.
     *
     * @return array An array of the default parameters
     */
    protected function getDefaultParameters()
    {
        return array(
            'cla' => 'Fo',
            'ss' => 'Class',
        );
    }

    /**
     * Applies all known interface injection calls
     *
     * @param Object $instance
     */
    protected function applyInterfaceInjectors($instance)
    {
        if ($instance instanceof \FooClass) {
            $instance->setBar('someValue');
        }
    }
}
