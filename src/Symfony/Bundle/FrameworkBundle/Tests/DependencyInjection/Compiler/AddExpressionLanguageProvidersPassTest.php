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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;

class AddExpressionLanguageProvidersPassTest extends TestCase
{
    public function testProcessForRouter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('\stdClass');
        $definition->addTag('routing.expression_language_provider');
        $container->setDefinition('some_routing_provider', $definition->setPublic(true));

        $container->register('router', '\stdClass')->setPublic(true);
        $container->compile();

        $router = $container->getDefinition('router');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_routing_provider'), $calls[0][1][0]);
    }

    public function testProcessForRouterAlias()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('\stdClass');
        $definition->addTag('routing.expression_language_provider');
        $container->setDefinition('some_routing_provider', $definition->setPublic(true));

        $container->register('my_router', '\stdClass')->setPublic(true);
        $container->setAlias('router', 'my_router');
        $container->compile();

        $router = $container->getDefinition('my_router');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_routing_provider'), $calls[0][1][0]);
    }

    public function testProcessForSecurity()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('\stdClass');
        $definition->addTag('security.expression_language_provider');
        $container->setDefinition('some_security_provider', $definition->setPublic(true));

        $container->register('security.expression_language', '\stdClass')->setPublic(true);
        $container->compile();

        $calls = $container->getDefinition('security.expression_language')->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('registerProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_security_provider'), $calls[0][1][0]);
    }

    public function testProcessForSecurityAlias()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('\stdClass');
        $definition->addTag('security.expression_language_provider');
        $container->setDefinition('some_security_provider', $definition->setPublic(true));

        $container->register('my_security.expression_language', '\stdClass')->setPublic(true);
        $container->setAlias('security.expression_language', 'my_security.expression_language');
        $container->compile();

        $calls = $container->getDefinition('my_security.expression_language')->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('registerProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_security_provider'), $calls[0][1][0]);
    }
}
