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
            'autowired.stdclass' => 'autowired.stdClass',
            'psr\\container\\containerinterface' => 'Psr\\Container\\ContainerInterface',
            'stdclass' => 'stdClass',
            'symfony\\component\\dependencyinjection\\containerinterface' => 'Symfony\\Component\\DependencyInjection\\ContainerInterface',
            'testservicesubscriber' => 'TestServiceSubscriber',
        );
        $this->methodMap = array(
            'TestServiceSubscriber' => 'getTestServiceSubscriberService',
            'autowired.stdClass' => 'getAutowired_StdClassService',
            'foo_service' => 'getFooServiceService',
        );
        $this->privates = array(
            'autowired.stdClass' => true,
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
     * Gets the 'TestServiceSubscriber' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \TestServiceSubscriber A TestServiceSubscriber instance
     */
    protected function getTestServiceSubscriberService()
    {
        return $this->services['TestServiceSubscriber'] = new \TestServiceSubscriber();
    }

    /**
     * Gets the 'foo_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * This service is autowired.
     *
     * @return \TestServiceSubscriber A TestServiceSubscriber instance
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \TestServiceSubscriber(new \Symfony\Component\DependencyInjection\ServiceLocator(array('TestServiceSubscriber' => function () {
            $f = function (\TestServiceSubscriber $v) { return $v; }; return $f(${($_ = isset($this->services['TestServiceSubscriber']) ? $this->services['TestServiceSubscriber'] : $this->get('TestServiceSubscriber')) && false ?: '_'});
        }, 'stdClass' => function () {
            $f = function (\stdClass $v = null) { return $v; }; return $f(${($_ = isset($this->services['autowired.stdClass']) ? $this->services['autowired.stdClass'] : $this->getAutowired_StdClassService()) && false ?: '_'});
        }, 'bar' => function () {
            $f = function (\stdClass $v) { return $v; }; return $f(${($_ = isset($this->services['autowired.stdClass']) ? $this->services['autowired.stdClass'] : $this->getAutowired_StdClassService()) && false ?: '_'});
        }, 'baz' => function () {
            $f = function (\stdClass $v = null) { return $v; }; return $f(${($_ = isset($this->services['autowired.stdClass']) ? $this->services['autowired.stdClass'] : $this->getAutowired_StdClassService()) && false ?: '_'});
        })));
    }

    /**
     * Gets the 'autowired.stdClass' service.
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
     * @return \stdClass A stdClass instance
     */
    protected function getAutowired_StdClassService()
    {
        return $this->services['autowired.stdClass'] = new \stdClass();
    }
}
