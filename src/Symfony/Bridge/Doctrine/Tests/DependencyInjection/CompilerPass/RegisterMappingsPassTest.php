<?php

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterMappingsPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageould Could not find the manager name parameter in the container. Tried the following parameter names: "manager.param.one", "manager.param.two"
     */
    public function testNoDriverParmeterException()
    {
        $container = $this->createBuilder([

        ]);
        $this->process($container, [
            'manager.param.one',
            'manager.param.two',
        ]);
    }

    private function process(ContainerBuilder $container, array $managerParamNames)
    {
        $pass = new ConcreteMappingsPass(
            new Definition('\stdClass'),
            [],
            $managerParamNames,
            'some.%s.metadata_driver'
        );

        $pass->process($container);
    }

    private function createBuilder()
    {
        $container = new ContainerBuilder();

        return $container;
    }
}

class ConcreteMappingsPass extends RegisterMappingsPass
{
}
