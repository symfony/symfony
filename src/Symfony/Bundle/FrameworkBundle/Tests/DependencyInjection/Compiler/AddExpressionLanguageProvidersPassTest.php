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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass;

class AddExpressionLanguageProvidersPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessForRouter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TestProvider');
        $definition->addTag('routing.expression_language_provider');
        $container->setDefinition('some_routing_provider', $definition);

        $container->register('router', '\stdClass');
        $container->compile();

        $router = $container->getDefinition('router');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_routing_provider'), $calls[0][1][0]);
    }

    public function testProcessForSecurity()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddExpressionLanguageProvidersPass());

        $definition = new Definition('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TestProvider');
        $definition->addTag('security.expression_language_provider');
        $container->setDefinition('some_security_provider', $definition);

        $container->register('security.access.expression_voter', '\stdClass');
        $container->compile();

        $router = $container->getDefinition('security.access.expression_voter');
        $calls = $router->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertEquals('addExpressionLanguageProvider', $calls[0][0]);
        $this->assertEquals(new Reference('some_security_provider'), $calls[0][1][0]);
    }
}

class TestProvider
{
}
