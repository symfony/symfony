<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->methodMap = array(
            'service_from_anonymous_factory' => 'getServiceFromAnonymousFactoryService',
            'service_with_method_call_and_factory' => 'getServiceWithMethodCallAndFactoryService',
        );
    }

    /**
     * Gets the 'service_from_anonymous_factory' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance.
     */
    protected function getServiceFromAnonymousFactoryService()
    {
        return $this->services['service_from_anonymous_factory'] = call_user_func(array(new \Bar\FooClass(), 'getInstance'));
    }

    /**
     * Gets the 'service_with_method_call_and_factory' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance.
     */
    protected function getServiceWithMethodCallAndFactoryService()
    {
        $this->services['service_with_method_call_and_factory'] = $instance = new \Bar\FooClass();

        $instance->setBar(\Bar\FooClass::getInstance());

        return $instance;
    }
}
