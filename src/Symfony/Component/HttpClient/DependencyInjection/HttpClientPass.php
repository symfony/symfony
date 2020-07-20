<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\TraceableHttpClient;

final class HttpClientPass implements CompilerPassInterface
{
    private $clientTag;

    public function __construct(string $clientTag = 'http_client.client')
    {
        $this->clientTag = $clientTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('data_collector.http_client')) {
            return;
        }

        foreach ($container->findTaggedServiceIds($this->clientTag) as $id => $tags) {
            $container->register('.debug.'.$id, TraceableHttpClient::class)
                ->setArguments([new Reference('.debug.'.$id.'.inner')])
                ->setDecoratedService($id);
            $container->getDefinition('data_collector.http_client')
                ->addMethodCall('registerClient', [$id, new Reference('.debug.'.$id)]);
        }
    }
}
