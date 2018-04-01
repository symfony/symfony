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
            'bar' => 'getBarService',
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
            'foo' => true,
        );
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        $a = new \stdClass();
        $a->add($this);

        return $this->services['bar'] = new \stdClass($a);
    }
}
