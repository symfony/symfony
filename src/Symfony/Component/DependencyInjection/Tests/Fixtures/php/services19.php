<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    public function __construct()
    {
        parent::__construct();
        $this->methodMap = array(
            'service_from_anonymous_factory' => 'getServiceFromAnonymousFactoryService',
            'service_with_method_call_and_factory' => 'getServiceWithMethodCallAndFactoryService',
        );
    }

    /**
     * Gets the public 'service_from_anonymous_factory' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getServiceFromAnonymousFactoryService()
    {
        return $this->services['service_from_anonymous_factory'] = call_user_func(array(new \Bar\FooClass(), 'getInstance'));
    }

    /**
     * Gets the public 'service_with_method_call_and_factory' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getServiceWithMethodCallAndFactoryService()
    {
        $this->services['service_with_method_call_and_factory'] = $instance = new \Bar\FooClass();

        $instance->setBar(\Bar\FooClass::getInstance());

        return $instance;
    }
}
