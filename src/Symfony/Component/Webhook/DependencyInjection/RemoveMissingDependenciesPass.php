<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * Drops the services that the container cannot wire, because what they depend on is missing.
 *
 * @internal
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('webhook.transport')) {
            return;
        }

        if (!$container->hasDefinition('webhook.payload_serializer.serializer')) {
            $container->getDefinition('webhook.body_configurator.json')
                ->replaceArgument(0, new Reference('webhook.payload_serializer.json'));
        }

        $transport = $container->getDefinition('webhook.transport');
        $httpClient = $container->hasDefinition('webhook.http_client') ? $container->getDefinition('webhook.http_client') : $transport;

        if (!$container->has((string) $httpClient->getArgument(0))) {
            $container->removeDefinition('webhook.http_client');
            $transport
                ->setArguments([])
                ->addError('You cannot use the "webhook transport" service since the HttpClient component is not '
                    .(class_exists(ScopingHttpClient::class) ? 'enabled. Try setting "framework.http_client.enabled" to true.' : 'installed. Try running "composer require symfony/http-client".')
                )
                ->addTag('container.error');
        }

        $controller = $container->getDefinition('webhook.controller');

        if (!$container->has((string) $controller->getArgument(1))) {
            $controller
                ->setArguments([])
                ->addError('You cannot use the "webhook controller" service since the message bus it is configured with does not exist.')
                ->addTag('container.error');
        }
    }
}
