<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
        parent::__construct();
        $this->methodMap = array(
            'depends_on_request' => 'getDependsOnRequestService',
            'request' => 'getRequestService',
        );
    }

    /**
     * Gets the public 'depends_on_request' shared service.
     *
     * @return \stdClass
     */
    protected function getDependsOnRequestService()
    {
        $this->services['depends_on_request'] = $instance = new \stdClass();

        $instance->setRequest($this->get('request', ContainerInterface::NULL_ON_INVALID_REFERENCE));

        return $instance;
    }

    /**
     * Gets the public 'request' shared service.
     *
     * @return \Request
     */
    protected function getRequestService()
    {
        return $this->services['request'] = new \Request();
    }

    /**
     * Updates the 'request' service.
     */
    protected function synchronizeRequestService()
    {
        if ($this->initialized('depends_on_request')) {
            $this->get('depends_on_request')->setRequest($this->get('request', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }
}
