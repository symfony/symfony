<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\RemoteEvent\RemoteEventBundle;
use Symfony\Component\Webhook\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Webhook\Server\SignatureFormat;

/**
 * Provides the services that send and receive webhooks.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(MessengerBundle::class, ignoreOnInvalid: true)]
#[RequiredBundle(RemoteEventBundle::class, ignoreOnInvalid: true)]
class WebhookBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->scalarNode('message_bus')->defaultValue('messenger.default_bus')->info('The message bus to use.')->end()
                ->scalarNode('http_client')->defaultValue('http_client')->info('The HTTP client to use to send webhooks.')->end()
                ->arrayNode('no_private_network')
                    ->info('Refuse to send webhooks to URLs that resolve to a private network.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('subnets')
                            ->info('Subnets in CIDR notation to consider private. Defaults to the standard private subnets.')
                            ->beforeNormalization()->ifString()->then(static fn ($v) => [$v])->end()
                            ->scalarPrototype()->end()
                            ->defaultNull()
                        ->end()
                        ->arrayNode('allow_list')
                            ->info('IPs or subnets in CIDR notation to send to even when they match the private subnets.')
                            ->beforeNormalization()->ifString()->then(static fn ($v) => [$v])->end()
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('event_header_name')->defaultValue('Webhook-Event')->end()
                ->scalarNode('id_header_name')->defaultValue('Webhook-Id')->end()
                ->scalarNode('timestamp_header_name')->defaultValue('Webhook-Timestamp')->end()
                ->scalarNode('signature_header_name')->defaultValue('Webhook-Signature')->end()
                ->scalarNode('signing_algorithm')->defaultValue('sha256')->end()
                ->enumNode('signature_format')
                    ->info('The signature scheme to emit and to require: "legacy" (default) for Symfony\'s historical "<algo>=<hex>" over the event name, the id and the body; "standard" for the Standard Webhooks "v1,<base64>" over the id, the timestamp and the body, which moves the event name from the "Webhook-Event" header to the payload\'s "type" key; "transitional" for both at once, during a migration.')
                    ->values(['legacy', 'standard', 'transitional'])
                    ->defaultValue('legacy')
                ->end()
                ->integerNode('timestamp_tolerance')
                    ->info('How far, in seconds, an incoming Standard Webhooks timestamp may be from the current time before the request is rejected as a replay. Set to 0 to accept any timestamp. Legacy signatures carry no timestamp and are never bounded.')
                    ->defaultValue(300)
                    ->min(0)
                ->end()
                ->arrayNode('routing')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('type')
                    ->prototype('array')
                        ->children()
                        ->scalarNode('service')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('secret')
                            ->defaultValue('')
                            ->info('The secret used to verify incoming request signatures. It must be set in production: with an empty value, requests from any sender are accepted.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/webhook.php');

        $parsers = [];
        foreach ($config['routing'] as $type => $cfg) {
            $parsers[$type] = [
                'parser' => new Reference($cfg['service']),
                'secret' => $cfg['secret'],
            ];
        }

        $controller = $container->getDefinition('webhook.controller');
        $controller->replaceArgument(0, $parsers);
        $controller->replaceArgument(1, new Reference($config['message_bus']));

        $signatureFormat = SignatureFormat::from($config['signature_format']);

        $container->getDefinition('webhook.body_configurator.json')
            ->replaceArgument(0, new Reference('webhook.payload_serializer.serializer'))
            ->replaceArgument(1, $signatureFormat);

        $container->getDefinition('webhook.headers_configurator')
            ->replaceArgument(0, $config['event_header_name'])
            ->replaceArgument(1, $config['id_header_name'])
            ->replaceArgument(2, $config['timestamp_header_name'])
            ->replaceArgument(4, $signatureFormat);

        $container->getDefinition('webhook.signer')
            ->replaceArgument(0, $config['signing_algorithm'])
            ->replaceArgument(1, $config['signature_header_name'])
            ->replaceArgument(2, $signatureFormat)
            ->replaceArgument(3, $config['timestamp_header_name']);

        $container->getDefinition('webhook.request_parser')
            ->replaceArgument(0, $config['signing_algorithm'])
            ->replaceArgument(1, $config['signature_header_name'])
            ->replaceArgument(2, $config['event_header_name'])
            ->replaceArgument(3, $config['id_header_name'])
            ->replaceArgument(4, $config['timestamp_header_name'])
            ->replaceArgument(5, $signatureFormat)
            ->replaceArgument(6, $config['timestamp_tolerance']);

        $clientId = $config['http_client'];

        if ($config['no_private_network']['enabled']) {
            if (!class_exists(NoPrivateNetworkHttpClient::class)) {
                throw new LogicException('Configuring "webhook.no_private_network" requires the HttpClient component. Try running "composer require symfony/http-client".');
            }

            $container->register('webhook.http_client', NoPrivateNetworkHttpClient::class)
                ->setArguments([
                    new Reference($clientId),
                    $config['no_private_network']['subnets'],
                    $config['no_private_network']['allow_list'],
                ])
                ->addTag('kernel.reset', ['method' => 'reset']);

            $clientId = 'webhook.http_client';
        }

        $container->getDefinition('webhook.transport')->replaceArgument(0, new Reference($clientId));
    }
}
