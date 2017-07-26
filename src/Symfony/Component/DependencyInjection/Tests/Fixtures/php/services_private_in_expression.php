<?php

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
            'private_foo' => 'getPrivateFooService',
            'public_foo' => 'getPublicFooService',
        );
        $this->privates = array(
            'private_foo' => true,
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped frozen container.');
    }

    /**
     * {@inheritdoc}
     */
    public function isFrozen()
    {
        return true;
    }

    /**
     * Gets the public 'public_foo' shared service.
     *
     * @return \stdClass
     */
    protected function getPublicFooService()
    {
        return $this->services['public_foo'] = new \stdClass(${($_ = isset($this->services['private_foo']) ? $this->services['private_foo'] : $this->getPrivateFooService()) && false ?: '_'});
    }

    /**
     * Gets the private 'private_foo' shared service.
     *
     * @return \stdClass
     */
    protected function getPrivateFooService()
    {
        return $this->services['private_foo'] = new \stdClass();
    }
}
