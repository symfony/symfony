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
            'App\\Bus' => 'getBusService',
            'App\\Db' => 'getDbService',
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
            'App\\Handler1' => true,
            'App\\Handler2' => true,
            'App\\Processor' => true,
            'App\\Registry' => true,
            'App\\Schema' => true,
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
        );
    }

    /**
     * Gets the public 'App\Bus' shared service.
     *
     * @return \App\Bus
     */
    protected function getBusService()
    {
        $a = ($this->services['App\Db'] ?? $this->getDbService());

        $this->services['App\Bus'] = $instance = new \App\Bus($a);

        $b = ($this->privates['App\Schema'] ?? $this->getSchemaService());
        $c = new \App\Registry();
        $c->processor = array(0 => $a, 1 => $instance);

        $d = new \App\Processor($c, $a);

        $instance->handler1 = new \App\Handler1($a, $b, $d);
        $instance->handler2 = new \App\Handler2($a, $b, $d);

        return $instance;
    }

    /**
     * Gets the public 'App\Db' shared service.
     *
     * @return \App\Db
     */
    protected function getDbService()
    {
        $this->services['App\Db'] = $instance = new \App\Db();

        $instance->schema = ($this->privates['App\Schema'] ?? $this->getSchemaService());

        return $instance;
    }

    /**
     * Gets the private 'App\Schema' shared service.
     *
     * @return \App\Schema
     */
    protected function getSchemaService()
    {
        $a = ($this->services['App\Db'] ?? $this->getDbService());

        if (isset($this->privates['App\Schema'])) {
            return $this->privates['App\Schema'];
        }

        return $this->privates['App\Schema'] = new \App\Schema($a);
    }
}
