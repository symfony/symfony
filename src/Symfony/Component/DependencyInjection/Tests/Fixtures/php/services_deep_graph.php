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
class Symfony_DI_PhpDumper_Test_Deep_Graph extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'bar' => 'getBarService',
            'foo' => 'getFooService',
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
        ];
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \stdClass
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \stdClass();

        $instance->p5 = new \stdClass(($this->services['foo'] ?? $this->getFooService()));

        return $instance;
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Dumper\FooForDeepGraph
     */
    protected function getFooService()
    {
        $a = ($this->services['bar'] ?? $this->getBarService());

        if (isset($this->services['foo'])) {
            return $this->services['foo'];
        }
        $b = new \stdClass();

        $c = new \stdClass();
        $c->p3 = new \stdClass();

        $b->p2 = $c;

        return $this->services['foo'] = new \Symfony\Component\DependencyInjection\Tests\Dumper\FooForDeepGraph($a, $b);
    }
}
