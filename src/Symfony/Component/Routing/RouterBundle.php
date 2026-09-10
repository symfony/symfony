<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Attribute\AsRouteLoader;
use Symfony\Component\Routing\Attribute\AsRoutingConditionService;
use Symfony\Component\Routing\DependencyInjection\LocalizedRoutesPass;

/**
 * Provides the router and the services that load and generate routes.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
class RouterBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new LocalizedRoutesPass());

        $container->registerAttributeForAutoconfiguration(AsRouteLoader::class, static function (ChildDefinition $definition): void {
            $definition->addTag('routing.route_loader');
        });
        $container->registerAttributeForAutoconfiguration(AsRoutingConditionService::class, static function (ChildDefinition $definition, AsRoutingConditionService $attribute): void {
            $definition->addTag('routing.condition_service', ['alias' => $attribute->alias, 'priority' => $attribute->priority]);
        });
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            // canBeEnabled() would enable the router for the empty configuration an auto-registered
            // bundle is loaded with: its "auto_enable" attribute makes ArrayNode::merge() force
            // "enabled" on, and its normalization does the same for an empty array. Everything else
            // it does is kept, so any non-empty configuration still enables the router implicitly.
            ->addDefaultsIfNotSet()
            ->treatFalseLike(['enabled' => false])
            ->treatTrueLike(['enabled' => true])
            ->treatNullLike(['enabled' => true])
            ->beforeNormalization()
                ->ifTrue(static fn ($v) => \is_array($v) && [] !== $v)
                ->then(static function ($v) {
                    $v['enabled'] ??= true;

                    return $v;
                })
            ->end()
            // the resource is only demanded once the router is actually enabled, so that the
            // empty configuration above stays valid
            ->validate()
                ->ifTrue(static fn ($v) => $v['enabled'] && null === $v['resource'])
                ->thenInvalid('The child config "resource" under "router" must be configured.')
            ->end()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('resource')->defaultNull()->end()
                ->scalarNode('type')->end()
                ->scalarNode('default_uri')
                    ->info('The default URI used to generate URLs in a non-HTTP context.')
                    ->defaultNull()
                ->end()
                ->scalarNode('http_port')->defaultValue(80)->end()
                ->scalarNode('https_port')->defaultValue(443)->end()
                ->scalarNode('strict_requirements')
                    ->info(
                        "set to true to throw an exception when a parameter does not match the requirements\n".
                        "set to false to disable exceptions when a parameter does not match the requirements (and return null instead)\n".
                        "set to null to disable parameter checks against requirements\n".
                        "'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production"
                    )
                    ->defaultTrue()
                ->end()
                ->booleanNode('utf8')->defaultTrue()->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        // Read the deprecated "router.request_context.{host,scheme}" parameters before routing.php
        // sets their defaults, so that an explicit user value takes precedence. They are inlined as
        // arguments of the "router.request_context" service below: this avoids both triggering their
        // deprecation and eagerly resolving every env-var-based parameter through ParameterBag::all()
        // at runtime.
        $parameters = $container->getParameterBag()->all();
        $requestContextHost = $parameters['router.request_context.host'] ?? 'localhost';
        $requestContextScheme = $parameters['router.request_context.scheme'] ?? 'http';

        $configurator->import('Resources/config/routing.php');

        if (class_exists(Application::class)) {
            $configurator->import('Resources/config/console.php');
        }

        $container->getDefinition('router.request_context')
            ->setArgument(1, $requestContextHost)
            ->setArgument(2, $requestContextScheme);

        $container->deprecateParameter('router.request_context.scheme', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.scheme" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');
        $container->deprecateParameter('router.request_context.host', 'symfony/framework-bundle', '8.1', 'Parameter "router.request_context.host" is deprecated, use "router.request_context.base_url" parameter or the "framework.router.default_uri" config option instead.');

        if ($config['utf8']) {
            $container->getDefinition('routing.loader')->replaceArgument(1, ['utf8' => true]);
        }

        if (!ContainerBuilder::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $container->removeDefinition('router.expression_language_provider');
        }

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_dir', '%kernel.build_dir%');
        $router = $container->findDefinition('router.default');
        $argument = $router->getArgument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replaceArgument(2, $argument);

        $container->setParameter('request_listener.http_port', $config['http_port']);
        $container->setParameter('request_listener.https_port', $config['https_port']);

        if (null !== $config['default_uri']) {
            $container->setParameter('router.request_context.base_url', $config['default_uri']);
        }
    }
}
