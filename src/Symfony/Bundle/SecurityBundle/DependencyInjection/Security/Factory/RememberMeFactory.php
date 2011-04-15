<?php

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;

use Symfony\Component\DependencyInjection\DefinitionDecorator;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RememberMeFactory implements SecurityFactoryInterface
{
    protected $options = array(
        'name' => 'REMEMBERME',
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'always_remember_me' => false,
        'remember_me_parameter' => '_remember_me',
    );

    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        // authentication provider
        $authProviderId = 'security.authentication.provider.rememberme.'.$id;
        $container
            ->setDefinition($authProviderId, new DefinitionDecorator('security.authentication.provider.rememberme'))
            ->addArgument($config['key'])
            ->addArgument($id)
        ;

        // remember me services
        if (isset($config['token_provider'])) {
            $config['token-provider'] = $config['token_provider'];
        }
        if (isset($config['token-provider'])) {
            $templateId = 'security.authentication.rememberme.services.persistent';
            $rememberMeServicesId = $templateId.'.'.$id;
        } else {
            $templateId = 'security.authentication.rememberme.services.simplehash';
            $rememberMeServicesId = $templateId.'.'.$id;
        }

        if ($container->hasDefinition('security.logout_listener.'.$id)) {
            $container
                ->getDefinition('security.logout_listener.'.$id)
                ->addMethodCall('addHandler', array(new Reference($rememberMeServicesId)))
            ;
        }

        $rememberMeServices = $container->setDefinition($rememberMeServicesId, new DefinitionDecorator($templateId));
        $rememberMeServices->setArgument(1, $config['key']);
        $rememberMeServices->setArgument(2, $id);

        if (isset($config['token-provider'])) {
            // FIXME: make the naming assumption more flexible
            $rememberMeServices->addMethodCall('setTokenProvider', array(
                new Reference('security.rememberme.token.provider.'.$config['token-provider'])
            ));
        }

        // remember-me options
        $rememberMeServices->setArgument(3, array_intersect_key($config, $this->options));

        // attach to remember-me aware listeners
        $userProviders = array();
        foreach ($container->findTaggedServiceIds('security.remember_me_aware') as $serviceId => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['id']) || $attribute['id'] !== $id) {
                    continue;
                }

                if (!isset($attribute['provider'])) {
                    throw new \RuntimeException('Each "security.remember_me_aware" tag must have a provider attribute.');
                }

                $userProviders[] = new Reference($attribute['provider']);
                $container
                    ->getDefinition($serviceId)
                    ->addMethodCall('setRememberMeServices', array(new Reference($rememberMeServicesId)))
                ;
            }
        }
        if (count($userProviders) === 0) {
            throw new \RuntimeException('You must configure at least one remember-me aware listener (such as form-login) for each firewall that has remember-me enabled.');
        }
        $rememberMeServices->setArgument(0, $userProviders);

        // remember-me listener
        $listenerId = 'security.authentication.listener.rememberme.'.$id;
        $listener = $container->setDefinition($listenerId, new DefinitionDecorator('security.authentication.listener.rememberme'));
        $listener->setArgument(1, new Reference($rememberMeServicesId));

        return array($authProviderId, $listenerId, $defaultEntryPoint);
    }

    public function getPosition()
    {
        return 'remember_me';
    }

    public function getKey()
    {
        return 'remember-me';
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $builder = $node->children();

        $builder
            ->scalarNode('key')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('token_provider')->end()
        ;

        foreach ($this->options as $name => $value) {
            if (is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }
}
