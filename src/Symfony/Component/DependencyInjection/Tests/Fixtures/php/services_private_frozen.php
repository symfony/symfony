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
            'bar_service' => 'getBarServiceService',
            'baz_service' => 'getBazServiceService',
            'foo_service' => 'getFooServiceService',
        );
        $this->privates = array(
            'baz_service' => true,
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
     * Gets the 'bar_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBarServiceService()
    {
        return $this->services['bar_service'] = new \stdClass(${($_ = isset($this->services['baz_service']) ? $this->services['baz_service'] : $this->getBazServiceService()) && false ?: '_'});
    }

    /**
     * Gets the 'foo_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \stdClass(${($_ = isset($this->services['baz_service']) ? $this->services['baz_service'] : $this->getBazServiceService()) && false ?: '_'});
    }

    /**
     * Gets the 'baz_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * This service is private.
     * If you want to be able to request this service from the container directly,
     * make it public, otherwise you might end up with broken code.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getBazServiceService()
    {
        return $this->services['baz_service'] = new \stdClass();
    }
}
