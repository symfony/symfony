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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveMissingHttpClientDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveMissingHttpClientDependenciesPassTest extends TestCase
{
    public function testEverythingIsKeptWhenTheClientExists()
    {
        $container = $this->createContainer();
        $container->register('http_client');

        new RemoveMissingHttpClientDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('translation.provider_factory.lokalise'));
        $this->assertSame([], $container->getDefinition('webhook.transport')->getErrors());
    }

    public function testTheTranslationProvidersAreDroppedWhenTheClientIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingHttpClientDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('translation.provider_factory.lokalise'));
        $this->assertTrue($container->hasDefinition('translation.provider_factory.null'));
    }

    public function testTheWebhookTransportFailsWhenTheClientIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingHttpClientDependenciesPass()->process($container);

        $definition = $container->getDefinition('webhook.transport');
        $this->assertTrue($definition->hasTag('container.error'));
        $this->assertStringContainsString('You cannot use the "webhook transport" service', $definition->getErrors()[0]);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('translation.provider_factory.null');
        $container->register('translation.provider_factory.lokalise');
        $container->register('webhook.transport');

        return $container;
    }
}
