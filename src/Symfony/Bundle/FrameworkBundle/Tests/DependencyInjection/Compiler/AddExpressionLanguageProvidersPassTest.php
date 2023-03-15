<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AddExpressionLanguageProvidersPassTest extends TestCase
{
    public function testProcessForRouter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition(\stdClass::class);
        $definition->addTag('routing.expression_language_provider');
        $container->setDefinition('some_routing_provider', $definition->setPublic(true));

        $container->register('router.default', \stdClass::class)->setPublic(true);
        $container->compile();

        $router = $container->getDefinition('router.default');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_routing_provider'), $calls[0][1][0]);
    }

    public function testProcessForRouterAlias()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition(\stdClass::class);
        $definition->addTag('routing.expression_language_provider');
        $container->setDefinition('some_routing_provider', $definition->setPublic(true));

        $container->register('my_router', \stdClass::class)->setPublic(true);
        $container->setAlias('router.default', 'my_router');
        $container->compile();

        $router = $container->getDefinition('my_router');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_routing_provider'), $calls[0][1][0]);
    }
}
