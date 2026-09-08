<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Webhook\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Webhook\WebhookBundle;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testThePayloadIsSerializedWithTheSerializerWhenItExists()
    {
        $container = $this->createContainer();
        $container->register('serializer');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertEquals(new Reference('webhook.payload_serializer.serializer'), $container->getDefinition('webhook.body_configurator.json')->getArgument(0));
        $this->assertTrue($container->hasDefinition('webhook.payload_serializer.serializer'));
    }

    public function testThePayloadIsSerializedWithJsonEncodeWhenTheSerializerIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertEquals(new Reference('webhook.payload_serializer.json'), $container->getDefinition('webhook.body_configurator.json')->getArgument(0));
        $this->assertFalse($container->hasDefinition('webhook.payload_serializer.serializer'));
    }

    public function testTheTransportIsKeptWhenTheHttpClientExists()
    {
        $container = $this->createContainer();
        $container->register('http_client');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->getDefinition('webhook.transport')->hasErrors());
        $this->assertEquals(new Reference('http_client'), $container->getDefinition('webhook.transport')->getArgument(0));
    }

    public function testTheTransportReportsAnErrorWhenTheHttpClientIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $transport = $container->getDefinition('webhook.transport');
        $this->assertSame([], $transport->getArguments());
        $this->assertStringContainsString('You cannot use the "webhook transport" service', $transport->getErrors()[0]);
        $this->assertSame([[]], $transport->getTag('container.error'));
    }

    public function testTheWrappingHttpClientIsDroppedWhenTheClientItWrapsIsMissing()
    {
        $container = $this->createContainer(['no_private_network' => true]);

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('webhook.http_client'));
        $this->assertTrue($container->getDefinition('webhook.transport')->hasErrors());
    }

    public function testTheWrappingHttpClientIsKeptWhenTheClientItWrapsExists()
    {
        $container = $this->createContainer(['no_private_network' => true]);
        $container->register('http_client');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('webhook.http_client'));
        $this->assertFalse($container->getDefinition('webhook.transport')->hasErrors());
    }

    public function testTheControllerIsKeptWhenTheMessageBusExists()
    {
        $container = $this->createContainer();
        $container->register('messenger.default_bus');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->getDefinition('webhook.controller')->hasErrors());
    }

    public function testTheControllerReportsAnErrorWhenTheMessageBusIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $controller = $container->getDefinition('webhook.controller');
        $this->assertSame([], $controller->getArguments());
        $this->assertStringContainsString('You cannot use the "webhook controller" service', $controller->getErrors()[0]);
        $this->assertSame([[]], $controller->getTag('container.error'));
    }

    public function testNothingHappensWhenTheBundleIsDisabled()
    {
        $container = $this->createContainer(['enabled' => false]);

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('webhook.transport'));
    }

    private function createContainer(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        new WebhookBundle()->getContainerExtension()->load([$config], $container);

        return $container;
    }
}
