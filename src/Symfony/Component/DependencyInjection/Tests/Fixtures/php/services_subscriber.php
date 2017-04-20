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
        $this->normalizedIds = array(
            'autowired.symfony\\component\\dependencyinjection\\tests\\fixtures\\customdefinition' => 'autowired.Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition',
            'symfony\\component\\dependencyinjection\\tests\\fixtures\\testservicesubscriber' => 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber',
        );
        $this->methodMap = array(
            'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => 'getSymfony_Component_DependencyInjection_Tests_Fixtures_TestServiceSubscriberService',
            'autowired.Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => 'getAutowired_Symfony_Component_DependencyInjection_Tests_Fixtures_CustomDefinitionService',
            'foo_service' => 'getFooServiceService',
        );
        $this->privates = array(
            'autowired.Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => true,
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
     * Gets the 'Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber A Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber instance
     */
    protected function getSymfony_Component_DependencyInjection_Tests_Fixtures_TestServiceSubscriberService()
    {
        return $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber();
    }

    /**
     * Gets the 'foo_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * This service is autowired.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber A Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber instance
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber(new \Symfony\Component\DependencyInjection\ServiceLocator(array('Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\CustomDefinition' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v) { return $v; }; return $f(${($_ = isset($this->services['autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition']) ? $this->services['autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] : $this->getAutowired_Symfony_Component_DependencyInjection_Tests_Fixtures_CustomDefinitionService()) && false ?: '_'});
        }, 'Symfony\\Component\\DependencyInjection\\Tests\\Fixtures\\TestServiceSubscriber' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber $v) { return $v; }; return $f(${($_ = isset($this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber']) ? $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] : $this->get('Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber')) && false ?: '_'});
        }, 'bar' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v) { return $v; }; return $f(${($_ = isset($this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber']) ? $this->services['Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber'] : $this->get('Symfony\Component\DependencyInjection\Tests\Fixtures\TestServiceSubscriber')) && false ?: '_'});
        }, 'baz' => function () {
            $f = function (\Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition $v) { return $v; }; return $f(${($_ = isset($this->services['autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition']) ? $this->services['autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] : $this->getAutowired_Symfony_Component_DependencyInjection_Tests_Fixtures_CustomDefinitionService()) && false ?: '_'});
        })));
    }

    /**
     * Gets the 'autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * This service is private.
     * If you want to be able to request this service from the container directly,
     * make it public, otherwise you might end up with broken code.
     *
     * This service is autowired.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition A Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition instance
     */
    protected function getAutowired_Symfony_Component_DependencyInjection_Tests_Fixtures_CustomDefinitionService()
    {
        return $this->services['autowired.Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition'] = new \Symfony\Component\DependencyInjection\Tests\Fixtures\CustomDefinition();
    }
}
