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

    /**
     * @internal but protected for BC on cache:clear
     */
    protected $privates = array();

    public function __construct()
    {
        $this->services = $this->privates = array();
        $this->methodMap = array(
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'getTestServiceSubscriberService',
            'foo_service' => 'getFooServiceService',
        );

        $this->aliases = array();
    }

    public function reset()
    {
        $this->privates = array();
        parent::reset();
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function getRemovedIds()
    {
        return array(
            '.service_locator.ljJrY4L' => true,
            '.service_locator.ljJrY4L.foo_service' => true,
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
        );
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getTestServiceSubscriberService()
    {
        return $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber();
    }

    /**
     * Gets the public 'foo_service' shared autowired service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber((new \Symfony\Component\DependencyInjection\Argument\ServiceLocator(\Closure::fromCallable(array($this, 'getService')), array(
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => array('privates', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition', 'getCustomDefinitionService', false),
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => array('services', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber', 'getTestServiceSubscriberService', false),
            'bar' => array('services', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber', 'getTestServiceSubscriberService', false),
            'baz' => array('privates', 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition', 'getCustomDefinitionService', false),
        )))->withContext('foo_service', $this));
    }

    /**
     * Gets the private 'Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition
     */
    protected function getCustomDefinitionService()
    {
        return $this->privates['Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition();
    }
}
