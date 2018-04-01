<?php

use Symphony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symphony\Component\DependencyInjection\Exception\LogicException;
use Symphony\Component\DependencyInjection\Exception\RuntimeException;
use Symphony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * This class has been auto-generated
 * by the Symphony Dependency Injection Component.
 *
 * @final since Symphony 3.3
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
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'getTestServiceSubscriberService',
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
            'Psr\\Container\\ContainerInterface' => true,
            'Symphony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
            'service_locator.ljJrY4L' => true,
            'service_locator.ljJrY4L.foo_service' => true,
        );
    }

    /**
     * Gets the public 'Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber' shared service.
     *
     * @return \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getTestServiceSubscriberService()
    {
        return $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber();
    }

    /**
     * Gets the public 'foo_service' shared autowired service.
     *
     * @return \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber((new \Symphony\Component\DependencyInjection\ServiceLocator(array('Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => function (): ?\Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->privates['Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] ?? $this->privates['Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition());
        }, 'Symphony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => function (): \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber {
            return ($this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] ?? $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber());
        }, 'bar' => function (): \Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] ?? $this->services['Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber());
        }, 'baz' => function (): ?\Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->privates['Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] ?? $this->privates['Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symphony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition());
        })))->withContext('foo_service', $this));
    }
}
