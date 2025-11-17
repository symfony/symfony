<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Bundle\FrameworkBundle\Routing\RedirectableCompiledUrlMatcher;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\Loader\ContainerLoader;
use Symfony\Component\Routing\Loader\DirectoryLoader;
use Symfony\Component\Routing\Loader\GlobFileLoader;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\ExpressionLanguageProvider;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\RouterInterface;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('router.request_context.host', 'localhost')
        ->set('router.request_context.scheme', 'http')
        ->set('router.request_context.base_url', '')
    ;

    $container->services()
        ->set('routing.resolver', LoaderResolver::class)

        ->set('routing.loader.yml', YamlFileLoader::class)
            ->args([
                service('file_locator'),
                '%kernel.environment%',
            ])
            ->tag('routing.loader')

        ->set('routing.loader.php', PhpFileLoader::class)
            ->args([
                service('file_locator'),
                '%kernel.environment%',
            ])
            ->tag('routing.loader')

        ->set('routing.loader.glob', GlobFileLoader::class)
            ->args([
                service('file_locator'),
                '%kernel.environment%',
            ])
            ->tag('routing.loader')

        ->set('routing.loader.directory', DirectoryLoader::class)
            ->args([
                service('file_locator'),
                '%kernel.environment%',
            ])
            ->tag('routing.loader')

        ->set('routing.loader.container', ContainerLoader::class)
            ->args([
                tagged_locator('routing.route_loader'),
                '%kernel.environment%',
            ])
            ->tag('routing.loader')

        ->set('routing.loader.attribute', AttributeRouteControllerLoader::class)
            ->args([
                '%kernel.environment%',
            ])
            ->tag('routing.loader', ['priority' => -10])

        ->set('routing.loader.attribute.services', AttributeServicesLoader::class)
            ->args([
                abstract_arg('classes tagged with "routing.controller"'),
            ])
            ->tag('routing.loader', ['priority' => -10])

        ->set('routing.loader.attribute.directory', AttributeDirectoryLoader::class)
            ->args([
                service('file_locator'),
                service('routing.loader.attribute'),
            ])
            ->tag('routing.loader', ['priority' => -10])

        ->set('routing.loader.attribute.file', AttributeFileLoader::class)
            ->args([
                service('file_locator'),
                service('routing.loader.attribute'),
            ])
            ->tag('routing.loader', ['priority' => -10])

        ->set('routing.loader.psr4', Psr4DirectoryLoader::class)
            ->args([
                service('file_locator'),
            ])
            ->tag('routing.loader', ['priority' => -10])

        ->set('routing.loader', DelegatingLoader::class)
            ->public()
            ->args([
                service('routing.resolver'),
                [], // Default options
                [], // Default requirements
            ])

        ->set('router.default', Router::class)
            ->args([
                service(ContainerInterface::class),
                param('router.resource'),
                [
                    'cache_dir' => param('router.cache_dir'),
                    'debug' => param('kernel.debug'),
                    'generator_class' => CompiledUrlGenerator::class,
                    'generator_dumper_class' => CompiledUrlGeneratorDumper::class,
                    'matcher_class' => RedirectableCompiledUrlMatcher::class,
                    'matcher_dumper_class' => CompiledUrlMatcherDumper::class,
                ],
                service('router.request_context')->ignoreOnInvalid(),
                service('parameter_bag')->ignoreOnInvalid(),
                service('logger')->ignoreOnInvalid(),
                param('kernel.default_locale'),
            ])
            ->call('setConfigCacheFactory', [
                service('config_cache_factory'),
            ])
            ->tag('monolog.logger', ['channel' => 'router'])
            ->tag('container.service_subscriber', ['id' => 'routing.loader'])
        ->alias('router', 'router.default')
            ->public()
        ->alias(RouterInterface::class, 'router')
        ->alias(UrlGeneratorInterface::class, 'router')
        ->alias(UrlMatcherInterface::class, 'router')
        ->alias(RequestContextAwareInterface::class, 'router')

        ->set('router.request_context', RequestContext::class)
            ->factory([RequestContext::class, 'fromUri'])
            ->args([
                param('router.request_context.base_url'),
                param('router.request_context.host'),
                param('router.request_context.scheme'),
                param('request_listener.http_port'),
                param('request_listener.https_port'),
            ])
            ->call('setParameters', [[
                '_functions' => service('router.expression_language_provider')->ignoreOnInvalid(),
                '_locale' => '%kernel.default_locale%',
            ]])
        ->alias(RequestContext::class, 'router.request_context')

        ->set('router.expression_language_provider', ExpressionLanguageProvider::class)
            ->args([
                tagged_locator('routing.expression_language_function', 'function'),
            ])
            ->tag('routing.expression_language_provider')

        ->set('router.cache_warmer', RouterCacheWarmer::class)
            ->args([service(ContainerInterface::class)])
            ->tag('container.service_subscriber', ['id' => 'router'])
            ->tag('kernel.cache_warmer')

        ->set('router_listener', RouterListener::class)
            ->args([
                service('router'),
                service('request_stack'),
                service('router.request_context')->ignoreOnInvalid(),
                service('logger')->ignoreOnInvalid(),
                param('kernel.project_dir'),
                param('kernel.debug'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'request'])

        ->set(RedirectController::class)
            ->public()
            ->args([
                service('router'),
                inline_service('int')
                    ->factory([service('router.request_context'), 'getHttpPort']),
                inline_service('int')
                    ->factory([service('router.request_context'), 'getHttpsPort']),
            ])

        ->set(TemplateController::class)
            ->args([
                service('twig')->ignoreOnInvalid(),
            ])
            ->public()
    ;
};
