<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\UnshareAuthenticationResultHandlersPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;

class UnshareAuthenticationResultHandlersPassTest extends TestCase
{
    public function testHandlersWiredIntoAuthenticatorsAreUnshared()
    {
        $container = new ContainerBuilder();
        $container->register('success_handler', DefaultAuthenticationSuccessHandler::class);
        $container->register('failure_handler', DefaultAuthenticationFailureHandler::class);
        $this->wireSuccessHandler($container, 'success_handler');
        $this->wireFailureHandler($container, 'failure_handler');

        (new UnshareAuthenticationResultHandlersPass())->process($container);

        $this->assertFalse($container->getDefinition('success_handler')->isShared());
        $this->assertFalse($container->getDefinition('failure_handler')->isShared());
    }

    public function testHandlersWithoutResolvedClassAreUnshared()
    {
        $container = new ContainerBuilder();
        $container->setParameter('success_handler.class', DefaultAuthenticationSuccessHandler::class);
        $container->register('parameterized_handler', '%success_handler.class%');
        $container->register('abstract_handler', DefaultAuthenticationSuccessHandler::class)->setAbstract(true);
        $container->setDefinition('child_handler', new ChildDefinition('abstract_handler'));
        $this->wireSuccessHandler($container, 'parameterized_handler');
        $this->wireSuccessHandler($container, 'child_handler', 'json_login');

        (new UnshareAuthenticationResultHandlersPass())->process($container);

        $this->assertFalse($container->getDefinition('parameterized_handler')->isShared());
        $this->assertFalse($container->getDefinition('child_handler')->isShared());
    }

    public function testDecoratorsAreUnshared()
    {
        $container = new ContainerBuilder();
        $container->register('success_handler', DefaultAuthenticationSuccessHandler::class);
        $container->register('decorating_handler', DefaultAuthenticationSuccessHandler::class)->setDecoratedService('success_handler');
        $container->register('outer_decorating_handler', DefaultAuthenticationSuccessHandler::class)->setDecoratedService('decorating_handler');
        $this->wireSuccessHandler($container, 'success_handler');

        (new UnshareAuthenticationResultHandlersPass())->process($container);

        $this->assertFalse($container->getDefinition('success_handler')->isShared());
        $this->assertFalse($container->getDefinition('decorating_handler')->isShared());
        $this->assertFalse($container->getDefinition('outer_decorating_handler')->isShared());
    }

    public function testStackLayersAreUnshared()
    {
        $container = new ContainerBuilder();
        $stack = new ChildDefinition('');
        $stack->addTag('container.stack');
        $stack->setArguments($layers = [
            new Definition(DefaultAuthenticationSuccessHandler::class),
            new Definition(DefaultAuthenticationSuccessHandler::class),
        ]);
        $container->setDefinition('success_handler', $stack);
        $this->wireSuccessHandler($container, 'success_handler');

        (new UnshareAuthenticationResultHandlersPass())->process($container);

        $this->assertFalse($layers[0]->isShared());
        $this->assertFalse($layers[1]->isShared());
    }

    public function testHandlersNotWiredIntoAuthenticatorsAreLeftShared()
    {
        $container = new ContainerBuilder();
        $container->register('success_handler', DefaultAuthenticationSuccessHandler::class);
        $container->register('unrelated_handler', DefaultAuthenticationSuccessHandler::class);
        $this->wireSuccessHandler($container, 'success_handler');

        (new UnshareAuthenticationResultHandlersPass())->process($container);

        $this->assertTrue($container->getDefinition('unrelated_handler')->isShared());
    }

    private function wireSuccessHandler(ContainerBuilder $container, string $handlerId, string $key = 'form_login'): void
    {
        $definition = new ChildDefinition('security.authentication.custom_success_handler');
        $definition->replaceArgument(0, new Reference($handlerId));

        $container->setDefinition('security.authentication.success_handler.main.'.$key, $definition);
    }

    private function wireFailureHandler(ContainerBuilder $container, string $handlerId, string $key = 'form_login'): void
    {
        $definition = new ChildDefinition('security.authentication.custom_failure_handler');
        $definition->replaceArgument(0, new Reference($handlerId));

        $container->setDefinition('security.authentication.failure_handler.main.'.$key, $definition);
    }
}
