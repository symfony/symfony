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
    private $targetDirs = [];

    public function __construct()
    {
        $this->services = [];
        $this->normalizedIds = [
            'symfony\\component\\dependencyinjection\\tests\\fixtures\\customdefinition' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition',
            'symfony\\component\\dependencyinjection\\tests\\fixtures\\testservicesubscriber' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber',
        ];
        $this->methodMap = [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => 'getCustomDefinitionService',
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'getTestServiceSubscriberService',
            'foo_service' => 'getFooServiceService',
        ];
        $this->privates = [
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
        ];

        $this->aliases = [];
    }

    public function getRemovedIds()
    {
        return [
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
            'service_locator.jmktfsv' => true,
            'service_locator.jmktfsv.foo_service' => true,
        ];
    }

    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled()
    {
        return true;
    }

    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);

        return true;
    }

    /**
     * Gets the public 'Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getTestServiceSubscriberService()
    {
        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber();
    }

    /**
     * Gets the public 'foo_service' shared autowired service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber((new \Symfony\Component\DependencyInjection\ServiceLocator(['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v = null) { return $v; }; return $f(${($_ = isset($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition']) ? $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] : ($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition())) && false ?: '_'});
        }, 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber $v) { return $v; }; return $f(${($_ = isset($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber']) ? $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] : ($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber())) && false ?: '_'});
        }, 'bar' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v) { return $v; }; return $f(${($_ = isset($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber']) ? $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] : ($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber())) && false ?: '_'});
        }, 'baz' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v = null) { return $v; }; return $f(${($_ = isset($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition']) ? $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] : ($this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition())) && false ?: '_'});
        }]))->withContext('foo_service', $this));
    }

    /**
     * Gets the private 'Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition
     */
    protected function getCustomDefinitionService()
    {
        return $this->services['Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition();
    }
}
