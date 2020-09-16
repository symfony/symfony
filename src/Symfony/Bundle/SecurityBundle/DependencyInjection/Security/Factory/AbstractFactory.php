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

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * AbstractFactory is the base class for all classes inheriting from
 * AbstractAuthenticationListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AbstractFactory implements SecurityFactoryInterface
{
    protected $options = [
        'check_path' => '/login_check',
        'use_forward' => false,
        'require_previous_session' => false,
        'login_path' => '/login',
    ];

    protected $defaultSuccessHandlerOptions = [
        'always_use_default_target_path' => false,
        'default_target_path' => '/',
        'login_path' => '/login',
        'target_path_parameter' => '_target_path',
        'use_referer' => false,
    ];

    protected $defaultFailureHandlerOptions = [
        'failure_path' => null,
        'failure_forward' => false,
        'login_path' => '/login',
        'failure_path_parameter' => '_failure_path',
    ];

    public function create(ContainerBuilder $container, string $id, array $config, string $userProviderId, ?string $defaultEntryPointId)
    {
        // authentication provider
        $authProviderId = $this->createAuthProvider($container, $id, $config, $userProviderId);

        // authentication listener
        $listenerId = $this->createListener($container, $id, $config, $userProviderId);

        // add remember-me aware tag if requested
        if ($this->isRememberMeAware($config)) {
            $container
                ->getDefinition($listenerId)
                ->addTag('security.remember_me_aware', ['id' => $id, 'provider' => $userProviderId])
            ;
        }

        // create entry point if applicable (optional)
        $entryPointId = $this->createEntryPoint($container, $id, $config, $defaultEntryPointId);

        return [$authProviderId, $listenerId, $entryPointId];
    }

    public function addConfiguration(NodeDefinition $node)
    {
        $builder = $node->children();

        $builder
            ->scalarNode('provider')->end()
            ->booleanNode('remember_me')->defaultTrue()->end()
            ->scalarNode('success_handler')->end()
            ->scalarNode('failure_handler')->end()
        ;

        foreach (array_merge($this->options, $this->defaultSuccessHandlerOptions, $this->defaultFailureHandlerOptions) as $name => $default) {
            if (\is_bool($default)) {
                $builder->booleanNode($name)->defaultValue($default);
            } else {
                $builder->scalarNode($name)->defaultValue($default);
            }
        }
    }

    final public function addOption(string $name, $default = null)
    {
        $this->options[$name] = $default;
    }

    /**
     * Subclasses must return the id of a service which implements the
     * AuthenticationProviderInterface.
     *
     * @return string never null, the id of the authentication provider
     */
    abstract protected function createAuthProvider(ContainerBuilder $container, string $id, array $config, string $userProviderId);

    /**
     * Subclasses must return the id of the abstract listener template.
     *
     * Listener definitions should inherit from the AbstractAuthenticationListener
     * like this:
     *
     *    <service id="my.listener.id"
     *             class="My\Concrete\Classname"
     *             parent="security.authentication.listener.abstract"
     *             abstract="true" />
     *
     * In the above case, this method would return "my.listener.id".
     *
     * @return string
     */
    abstract protected function getListenerId();

    /**
     * Subclasses may create an entry point of their as they see fit. The
     * default implementation does not change the default entry point.
     *
     * @return string|null the entry point id
     */
    protected function createEntryPoint(ContainerBuilder $container, string $id, array $config, ?string $defaultEntryPointId)
    {
        return $defaultEntryPointId;
    }

    /**
     * Subclasses may disable remember-me features for the listener, by
     * always returning false from this method.
     *
     * @return bool Whether a possibly configured RememberMeServices should be set for this listener
     */
    protected function isRememberMeAware(array $config)
    {
        return $config['remember_me'];
    }

    protected function createListener(ContainerBuilder $container, string $id, array $config, string $userProvider)
    {
        $listenerId = $this->getListenerId();
        $listener = new ChildDefinition($listenerId);
        $listener->replaceArgument(4, $id);
        $listener->replaceArgument(5, new Reference($this->createAuthenticationSuccessHandler($container, $id, $config)));
        $listener->replaceArgument(6, new Reference($this->createAuthenticationFailureHandler($container, $id, $config)));
        $listener->replaceArgument(7, array_intersect_key($config, $this->options));

        $listenerId .= '.'.$id;
        $container->setDefinition($listenerId, $listener);

        return $listenerId;
    }

    protected function createAuthenticationSuccessHandler(ContainerBuilder $container, string $id, array $config)
    {
        $successHandlerId = $this->getSuccessHandlerId($id);
        $options = array_intersect_key($config, $this->defaultSuccessHandlerOptions);

        if (isset($config['success_handler'])) {
            $successHandler = $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.custom_success_handler'));
            $successHandler->replaceArgument(0, new Reference($config['success_handler']));
            $successHandler->replaceArgument(1, $options);
            $successHandler->replaceArgument(2, $id);
        } else {
            $successHandler = $container->setDefinition($successHandlerId, new ChildDefinition('security.authentication.success_handler'));
            $successHandler->addMethodCall('setOptions', [$options]);
            $successHandler->addMethodCall('setFirewallName', [$id]);
        }

        return $successHandlerId;
    }

    protected function createAuthenticationFailureHandler(ContainerBuilder $container, string $id, array $config)
    {
        $id = $this->getFailureHandlerId($id);
        $options = array_intersect_key($config, $this->defaultFailureHandlerOptions);

        if (isset($config['failure_handler'])) {
            $failureHandler = $container->setDefinition($id, new ChildDefinition('security.authentication.custom_failure_handler'));
            $failureHandler->replaceArgument(0, new Reference($config['failure_handler']));
            $failureHandler->replaceArgument(1, $options);
        } else {
            $failureHandler = $container->setDefinition($id, new ChildDefinition('security.authentication.failure_handler'));
            $failureHandler->addMethodCall('setOptions', [$options]);
        }

        return $id;
    }

    protected function getSuccessHandlerId(string $id)
    {
        return 'security.authentication.success_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }

    protected function getFailureHandlerId(string $id)
    {
        return 'security.authentication.failure_handler.'.$id.'.'.str_replace('-', '_', $this->getKey());
    }
}
