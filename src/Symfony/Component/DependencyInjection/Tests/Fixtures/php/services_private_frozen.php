<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();
    private $privates = array();

    public function __construct()
    {
        $this->services = $this->privates = array();
        $this->methodMap = array(
            'bar_service' => 'getBarServiceService',
            'foo_service' => 'getFooServiceService',
        );

        $this->aliases = array();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->privates = array();
        parent::reset();
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
     * Gets the public 'bar_service' shared service.
     *
     * @return \stdClass
     */
    protected function getBarServiceService()
    {
        return $this->services['bar_service'] = new \stdClass(($this->privates['baz_service'] ?? $this->privates['baz_service'] = new \stdClass()));
    }

    /**
     * Gets the public 'foo_service' shared service.
     *
     * @return \stdClass
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \stdClass(($this->privates['baz_service'] ?? $this->privates['baz_service'] = new \stdClass()));
    }
}
