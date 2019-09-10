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
class Symfony_DI_PhpDumper_Service_Wither extends Container
{
    private $parameters = [];

    public function __construct()
    {
        $this->services = $this->privates = [];
        $this->methodMap = [
            'wither' => 'getWitherService',
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
            'Symfony\\Component\\DependencyInjection\\Tests\\Compiler\\Foo' => true,
        ];
    }

    /**
     * Gets the public 'wither' shared autowired service.
     *
     * @return \Symfony\Component\DependencyInjection\Tests\Compiler\Wither
     */
    protected function getWitherService()
    {
        $instance = new \Symfony\Component\DependencyInjection\Tests\Compiler\Wither();

        $a = new \Symfony\Component\DependencyInjection\Tests\Compiler\Foo();

        $instance = $instance->withFoo1($a);
        $this->services['wither'] = $instance = $instance->withFoo2($a);
        $instance->setFoo($a);

        return $instance;
    }
}
