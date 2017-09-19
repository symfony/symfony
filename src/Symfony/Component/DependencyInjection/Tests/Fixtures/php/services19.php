<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
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
        $this->services = array();
        $this->methodMap = array(
            'service_from_anonymous_factory' => 'getServiceFromAnonymousFactoryService',
            'service_with_method_call_and_factory' => 'getServiceWithMethodCallAndFactoryService',
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since version 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the public 'service_from_anonymous_factory' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getServiceFromAnonymousFactoryService()
    {
        return $this->services['service_from_anonymous_factory'] = (new \Bar\FooClass())->getInstance();
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
