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
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber((new \Symfony\Component\DependencyInjection\ServiceLocator(array('Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => function (): ?\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->privates['Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] ?? $this->privates['Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition());
        }, 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => function (): \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber {
            return ($this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] ?? $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber());
        }, 'bar' => function (): \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] ?? $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber());
        }, 'baz' => function (): ?\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition {
            return ($this->privates['Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] ?? $this->privates['Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition());
        })))->withContext('foo_service', $this));
    }
}
