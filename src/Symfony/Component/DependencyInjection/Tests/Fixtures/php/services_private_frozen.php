<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final
 */
class ProjectServiceContainer extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar_service' => 'getBarServiceService',
            'foo_service' => 'getFooServiceService',
        ];

        $this->aliases = [];
    }

    public function compile(): void
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }

    public function isCompiled(): bool
    {
        return true;
    }

    public function getRemovedIds(): array
    {
        return [
            'Psr\\Container\\ContainerInterface' => true,
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => true,
            'baz_service' => true,
        ];
    }

    /**
     * Gets the public 'bar_service' shared service.
     *
     * @return \stdClass
     */
    protected function getBarServiceService()
    {
        return $this->services['bar_service'] = new \stdClass(($this->privates['baz_service'] ?? ($this->privates['baz_service'] = new \stdClass())));
    }

    /**
     * Gets the public 'foo_service' shared service.
     *
     * @return \stdClass
     */
    protected function getFooServiceService()
    {
        return $this->services['foo_service'] = new \stdClass(($this->privates['baz_service'] ?? ($this->privates['baz_service'] = new \stdClass())));
    }
}
