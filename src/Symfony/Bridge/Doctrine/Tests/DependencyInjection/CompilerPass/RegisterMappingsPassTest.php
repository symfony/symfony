<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterMappingsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RegisterMappingsPassTest extends TestCase
{
    public function testNoDriverParmeterException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find the manager name parameter in the container. Tried the following parameter names: "manager.param.one", "manager.param.two"');
        $container = $this->createBuilder();
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
