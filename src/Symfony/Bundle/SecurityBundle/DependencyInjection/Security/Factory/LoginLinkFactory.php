<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * @internal
 */
class LoginLinkFactory extends AbstractFactory
{
    public const PRIORITY = -20;

    public function addConfiguration(NodeDefinition $node)
    {
        /** @var NodeBuilder $builder */
        $builder = $node->fixXmlConfig('signature_property', 'signature_properties')->children();

        $builder
            ->scalarNode('check_route')
                ->isRequired()
                ->info('Route that will validate the login link - e.g. "app_login_link_verify".')
            ->end()
            ->scalarNode('check_post_only')
                ->defaultFalse()
                ->info('If true, only HTTP POST requests to "check_route" will be handled by the authenticator.')
            ->end()
            ->arrayNode('signature_properties')
                ->isRequired()
                ->prototype('scalar')->end()
                ->requiresAtLeastOneElement()
                ->info('An array of properties on your User that are used to sign the link. If any of these change, all existing links will become invalid.')
                ->example(['email', 'password'])
            ->end()
            ->integerNode('lifetime')
                ->defaultValue(600)
                ->info('The lifetime of the login link in seconds.')
            ->end()
            ->integerNode('max_uses')
                ->defaultNull()
                ->info('Max number of times a login link can be used - null means unlimited within lifetime.')
            ->end()
            ->scalarNode('used_link_cache')
                ->info('Cache service id used to expired links of max_uses is set.')
            ->end()
            ->scalarNode('success_handler')
                ->info(sprintf('A service id that implements %s.', AuthenticationSuccessHandlerInterface::class))
            ->end()
            ->scalarNode('failure_handler')
                ->info(sprintf('A service id that implements %s.', AuthenticationFailureHandlerInterface::class))
            ->end()
            ->scalarNode('provider')
                ->info('The user provider to load users from.')
            ->end()
        ;

        foreach (array_merge($this->defaultSuccessHandlerOptions, $this->defaultFailureHandlerOptions) as $name => $default) {
            if (\is_bool($default)) {
                $builder->booleanNode($name)->defaultValue($default);
            } else {
                $builder->scalarNode($name)->defaultValue($default);
            }
        }
    }

    public function getKey(): string
    {
        return 'login-link';
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        if (!$container->hasDefinition('security.authenticator.login_link')) {
            $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/../../Resources/config'));
            $loader->load('security_authenticator_login_link.php');
        }

        if (null !== $config['max_uses'] && !isset($config['used_link_cache'])) {
            $config['used_link_cache'] = 'security.authenticator.cache.expired_links';
            $defaultCacheDefinition = $container->getDefinition($config['used_link_cache']);
            if (!$defaultCacheDefinition->hasTag('cache.pool')) {
                $defaultCacheDefinition->addTag('cache.pool');
            }
        }

        $expiredStorageId = null;
        if (isset($config['used_link_cache'])) {
            $expiredStorageId = 'security.authenticator.expired_login_link_storage.'.$firewallName;
            $container
                ->setDefinition($expiredStorageId, new ChildDefinition('security.authenticator.expired_login_link_storage'))
                ->replaceArgument(0, new Reference($config['used_link_cache']))
                ->replaceArgument(1, $config['lifetime']);
        }

        $signatureHasherId = 'security.authenticator.login_link_signature_hasher.'.$firewallName;
        $container
            ->setDefinition($signatureHasherId, new ChildDefinition('security.authenticator.abstract_login_link_signature_hasher'))
            ->replaceArgument(1, $config['signature_properties'])
            ->replaceArgument(3, $expiredStorageId ? new Reference($expiredStorageId) : null)
            ->replaceArgument(4, $config['max_uses'] ?? null)
        ;

        $linkerId = 'security.authenticator.login_link_handler.'.$firewallName;
        $linkerOptions = [
            'route_name' => $config['check_route'],
            'lifetime' => $config['lifetime'],
        ];
        $container
            ->setDefinition($linkerId, new ChildDefinition('security.authenticator.abstract_login_link_handler'))
            ->replaceArgument(1, new Reference($userProviderId))
            ->replaceArgument(2, new Reference($signatureHasherId))
            ->replaceArgument(3, $linkerOptions)
            ->addTag('security.authenticator.login_linker', ['firewall' => $firewallName])
        ;

        $authenticatorId = 'security.authenticator.login_link.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.login_link'))
            ->replaceArgument(0, new Reference($linkerId))
            ->replaceArgument(2, new Reference($this->createAuthenticationSuccessHandler($container, $firewallName, $config)))
            ->replaceArgument(3, new Reference($this->createAuthenticationFailureHandler($container, $firewallName, $config)))
            ->replaceArgument(4, [
                'check_route' => $config['check_route'],
                'check_post_only' => $config['check_post_only'],
            ]);

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }
}
