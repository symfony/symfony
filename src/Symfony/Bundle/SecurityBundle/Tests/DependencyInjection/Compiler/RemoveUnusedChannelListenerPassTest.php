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
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RemoveUnusedChannelListenerPass;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\ChannelListener;

class RemoveUnusedChannelListenerPassTest extends TestCase
{
    public function testChannelListenerIsRemovedWhenNoRuleRequiresAChannel()
    {
        $container = $this->createContainer();
        $container->getDefinition('security.access_map')
            ->addMethodCall('add', [new Definition(PathRequestMatcher::class, ['^/admin']), ['ROLE_ADMIN'], null])
            ->addMethodCall('add', [new Definition(PathRequestMatcher::class, ['^/me']), ['ROLE_USER']]);

        (new RemoveUnusedChannelListenerPass())->process($container);

        $this->assertEquals([new Reference('security.access_listener')], $container->getDefinition('security.firewall.map.context.main')->getArgument(0)->getValues());
        $this->assertEquals([new Reference('security.access_listener')], $container->getDefinition('security.firewall.map.context.api')->getArgument(0)->getValues());
    }

    public function testChannelListenerIsKeptWhenARuleRequiresAChannel()
    {
        $container = $this->createContainer();
        $container->getDefinition('security.access_map')
            ->addMethodCall('add', [new Definition(PathRequestMatcher::class, ['^/admin']), ['ROLE_ADMIN'], null])
            ->addMethodCall('add', [new Definition(PathRequestMatcher::class, ['^/login']), [], 'https']);

        (new RemoveUnusedChannelListenerPass())->process($container);

        $this->assertEquals([new Reference('security.channel_listener'), new Reference('security.access_listener')], $container->getDefinition('security.firewall.map.context.main')->getArgument(0)->getValues());
    }

    public function testChannelListenerIsKeptWhenTheAccessMapIsReplaced()
    {
        $container = $this->createContainer();
        $container->getDefinition('security.access_map')->setClass(CustomAccessMap::class);

        (new RemoveUnusedChannelListenerPass())->process($container);

        $this->assertEquals([new Reference('security.channel_listener'), new Reference('security.access_listener')], $container->getDefinition('security.firewall.map.context.main')->getArgument(0)->getValues());
    }

    public function testChannelListenerIsKeptWhenTheAccessMapIsConfigured()
    {
        $container = $this->createContainer();
        $container->getDefinition('security.access_map')->setConfigurator([new Reference('app.access_map_configurator'), 'configure']);

        (new RemoveUnusedChannelListenerPass())->process($container);

        $this->assertEquals([new Reference('security.channel_listener'), new Reference('security.access_listener')], $container->getDefinition('security.firewall.map.context.main')->getArgument(0)->getValues());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('security.firewalls', ['main', 'api']);
        $container->register('security.access_map', AccessMap::class);
        $container->register('security.channel_listener', ChannelListener::class);
        $container->register('security.access_listener', AccessListener::class);

        foreach (['main', 'api'] as $firewall) {
            $container->register('security.firewall.map.context.'.$firewall, FirewallContext::class)
                ->setArgument(0, new IteratorArgument([new Reference('security.channel_listener'), new Reference('security.access_listener')]));
        }

        return $container;
    }
}

class CustomAccessMap implements AccessMapInterface
{
    public function getPatterns(Request $request): array
    {
        return [null, 'https'];
    }
}
