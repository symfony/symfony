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

use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;
use Symfony\Bundle\SecurityBundle\RememberMe\DecoratedRememberMeHandler;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\RememberMe\CacheTokenVerifier;

/**
 * @internal
 */
class RememberMeFactory implements AuthenticatorFactoryInterface, PrependExtensionInterface
{
    public const PRIORITY = -50;

    protected $options = [
        'name' => 'REMEMBERME',
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'samesite' => null,
        'always_remember_me' => false,
        'remember_me_parameter' => '_remember_me',
    ];

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        if (!$container->hasDefinition('security.authenticator.remember_me')) {
            $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/../../Resources/config'));
            $loader->load('security_authenticator_remember_me.php');
        }

        if ('auto' === $config['secure']) {
            $config['secure'] = null;
        }

        // create remember me handler (which manage the remember-me cookies)
        $rememberMeHandlerId = 'security.authenticator.remember_me_handler.'.$firewallName;
        if (isset($config['service']) && isset($config['token_provider'])) {
            throw new InvalidConfigurationException(sprintf('You cannot use both "service" and "token_provider" in "security.firewalls.%s.remember_me".', $firewallName));
        }

        if (isset($config['service'])) {
            $container->register($rememberMeHandlerId, DecoratedRememberMeHandler::class)
                ->addArgument(new Reference($config['service']))
                ->addTag('security.remember_me_handler', ['firewall' => $firewallName]);
        } elseif (isset($config['token_provider'])) {
            $tokenProviderId = $this->createTokenProvider($container, $firewallName, $config['token_provider']);
            $tokenVerifier = $this->createTokenVerifier($container, $firewallName, $config['token_verifier'] ?? null);
            $container->setDefinition($rememberMeHandlerId, new ChildDefinition('security.authenticator.persistent_remember_me_handler'))
                ->replaceArgument(0, new Reference($tokenProviderId))
                ->replaceArgument(1, new Reference($userProviderId))
                ->replaceArgument(3, $config)
                ->replaceArgument(5, $tokenVerifier)
                ->addTag('security.remember_me_handler', ['firewall' => $firewallName]);
        } else {
            $signatureHasherId = 'security.authenticator.remember_me_signature_hasher.'.$firewallName;
            $container->setDefinition($signatureHasherId, new ChildDefinition('security.authenticator.remember_me_signature_hasher'))
                ->replaceArgument(1, $config['signature_properties'])
                ->replaceArgument(2, $config['secret'])
            ;

            $container->setDefinition($rememberMeHandlerId, new ChildDefinition('security.authenticator.signature_remember_me_handler'))
                ->replaceArgument(0, new Reference($signatureHasherId))
                ->replaceArgument(1, new Reference($userProviderId))
                ->replaceArgument(3, $config)
                ->addTag('security.remember_me_handler', ['firewall' => $firewallName]);
        }

        // create check remember me conditions listener (which checks if a remember-me cookie is supported and requested)
        $rememberMeConditionsListenerId = 'security.listener.check_remember_me_conditions.'.$firewallName;
        $container->setDefinition($rememberMeConditionsListenerId, new ChildDefinition('security.listener.check_remember_me_conditions'))
            ->replaceArgument(0, array_intersect_key($config, ['always_remember_me' => true, 'remember_me_parameter' => true]))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName])
        ;

        // create remember me listener (which executes the remember me services for other authenticators and logout)
        $rememberMeListenerId = 'security.listener.remember_me.'.$firewallName;
        $container->setDefinition($rememberMeListenerId, new ChildDefinition('security.listener.remember_me'))
            ->replaceArgument(0, new Reference($rememberMeHandlerId))
            ->addTag('kernel.event_subscriber', ['dispatcher' => 'security.event_dispatcher.'.$firewallName])
        ;

        // create remember me authenticator (which re-authenticates the user based on the remember-me cookie)
        $authenticatorId = 'security.authenticator.remember_me.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition('security.authenticator.remember_me'))
            ->replaceArgument(0, new Reference($rememberMeHandlerId))
            ->replaceArgument(3, $config['name'] ?? $this->options['name'])
        ;

        return $authenticatorId;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getKey(): string
    {
        return 'remember-me';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $builder = $node
            ->fixXmlConfig('user_provider')
            ->children()
        ;

        $builder
            ->scalarNode('secret')
                ->cannotBeEmpty()
                ->defaultValue('%kernel.secret%')
            ->end()
            ->scalarNode('service')->end()
            ->arrayNode('user_providers')
                ->beforeNormalization()
                    ->ifString()->then(fn ($v) => [$v])
                ->end()
                ->prototype('scalar')->end()
            ->end()
            ->booleanNode('catch_exceptions')->defaultTrue()->end()
            ->arrayNode('signature_properties')
                ->prototype('scalar')->end()
                ->requiresAtLeastOneElement()
                ->info('An array of properties on your User that are used to sign the remember-me cookie. If any of these change, all existing cookies will become invalid.')
                ->example(['email', 'password'])
                ->defaultValue(['password'])
            ->end()
            ->arrayNode('token_provider')
                ->beforeNormalization()
                    ->ifString()->then(fn ($v) => ['service' => $v])
                ->end()
                ->children()
                    ->scalarNode('service')->info('The service ID of a custom rememberme token provider.')->end()
                    ->arrayNode('doctrine')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('connection')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->scalarNode('token_verifier')
                ->info('The service ID of a custom rememberme token verifier.')
            ->end();

        foreach ($this->options as $name => $value) {
            if ('secure' === $name) {
                $builder->enumNode($name)->values([true, false, 'auto'])->defaultValue('auto' === $value ? null : $value);
            } elseif ('samesite' === $name) {
                $builder->enumNode($name)->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultValue($value);
            } elseif (\is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } elseif (\is_int($value)) {
                $builder->integerNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }

    private function createTokenProvider(ContainerBuilder $container, string $firewallName, array $config): string
    {
        $tokenProviderId = $config['service'] ?? false;
        if ($config['doctrine']['enabled'] ?? false) {
            if (!class_exists(DoctrineTokenProvider::class)) {
                throw new InvalidConfigurationException('Cannot use the "doctrine" token provider for "remember_me" because the Doctrine Bridge is not installed. Try running "composer require symfony/doctrine-bridge".');
            }

            if (null === $config['doctrine']['connection']) {
                $connectionId = 'database_connection';
            } else {
                $connectionId = 'doctrine.dbal.'.$config['doctrine']['connection'].'_connection';
            }

            $tokenProviderId = 'security.remember_me.doctrine_token_provider.'.$firewallName;
            $container->register($tokenProviderId, DoctrineTokenProvider::class)
                ->addArgument(new Reference($connectionId));
        }

        if (!$tokenProviderId) {
            throw new InvalidConfigurationException(sprintf('No token provider was set for firewall "%s". Either configure a service ID or set "remember_me.token_provider.doctrine" to true.', $firewallName));
        }

        return $tokenProviderId;
    }

    private function createTokenVerifier(ContainerBuilder $container, string $firewallName, ?string $serviceId): Reference
    {
        if ($serviceId) {
            return new Reference($serviceId);
        }

        $tokenVerifierId = 'security.remember_me.token_verifier.'.$firewallName;

        $container->register($tokenVerifierId, CacheTokenVerifier::class)
            ->addArgument(new Reference('cache.security_token_verifier', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addArgument(60)
            ->addArgument('rememberme-'.$firewallName.'-stale-');

        return new Reference($tokenVerifierId, ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $rememberMeSecureDefault = false;
        $rememberMeSameSiteDefault = null;

        if (!isset($container->getExtensions()['framework'])) {
            return;
        }

        foreach ($container->getExtensionConfig('framework') as $config) {
            if (isset($config['session']) && \is_array($config['session'])) {
                $rememberMeSecureDefault = $config['session']['cookie_secure'] ?? $rememberMeSecureDefault;
                $rememberMeSameSiteDefault = \array_key_exists('cookie_samesite', $config['session']) ? $config['session']['cookie_samesite'] : $rememberMeSameSiteDefault;
            }
        }

        $this->options['secure'] = $rememberMeSecureDefault;
        $this->options['samesite'] = $rememberMeSameSiteDefault;
    }
}
