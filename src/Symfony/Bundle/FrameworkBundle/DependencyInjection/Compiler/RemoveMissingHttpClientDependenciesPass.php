<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * Drops the services that the container cannot wire, because the HTTP client is missing.
 *
 * @internal
 */
class RemoveMissingHttpClientDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('http_client')) {
            return;
        }

        foreach ([
            'translation.provider_factory.crowdin',
            'translation.provider_factory.crowdin.http_client',
            'translation.provider_factory.loco',
            'translation.provider_factory.loco.http_client',
            'translation.provider_factory.lokalise',
            'translation.provider_factory.phrase',
            'translation.provider_factory.poeditor',
        ] as $id) {
            $container->removeDefinition($id);
        }

        if ($container->hasDefinition('webhook.transport')) {
            $container->getDefinition('webhook.transport')
                ->setArguments([])
                ->addError('You cannot use the "webhook transport" service since the HttpClient component is not '
                    .(class_exists(ScopingHttpClient::class) ? 'enabled. Try setting "http_client.enabled" to true.' : 'installed. Try running "composer require symfony/http-client".')
                )
                ->addTag('container.error');
        }
    }
}
