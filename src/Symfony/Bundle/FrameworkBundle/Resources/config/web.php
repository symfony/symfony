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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\TemplateController;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DateTimeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ErrorController;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\CacheAttributeListener;
use Symfony\Component\HttpKernel\EventListener\DisallowRobotsIndexingListener;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\LocaleListener;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('controller_resolver', ControllerResolver::class)
            ->args([
                service('service_container'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->call('allowControllers', [[AbstractController::class, TemplateController::class]])
            ->tag('monolog.logger', ['channel' => 'request'])

        ->set('argument_metadata_factory', ArgumentMetadataFactory::class)

        ->set('argument_resolver', ArgumentResolver::class)
            ->args([
                service('argument_metadata_factory'),
                abstract_arg('argument value resolvers'),
                abstract_arg('targeted value resolvers'),
            ])

        ->set('argument_resolver.backed_enum_resolver', BackedEnumValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => BackedEnumValueResolver::class])

        ->set('argument_resolver.uid', UidValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => UidValueResolver::class])

        ->set('argument_resolver.datetime', DateTimeValueResolver::class)
            ->args([
                service('clock')->nullOnInvalid(),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => DateTimeValueResolver::class])

        ->set('argument_resolver.request_payload', RequestPayloadValueResolver::class)
            ->args([
                service('serializer'),
                service('validator')->nullOnInvalid(),
                service('translator')->nullOnInvalid(),
                param('validator.translation_domain'),
            ])
            ->tag('controller.targeted_value_resolver', ['name' => RequestPayloadValueResolver::class])
            ->tag('kernel.event_subscriber')
            ->lazy()

        ->set('argument_resolver.request_attribute', RequestAttributeValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => RequestAttributeValueResolver::class])

        ->set('argument_resolver.request', RequestValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => RequestValueResolver::class])

        ->set('argument_resolver.session', SessionValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => SessionValueResolver::class])

        ->set('argument_resolver.service', ServiceValueResolver::class)
            ->args([
                abstract_arg('service locator, set in RegisterControllerArgumentLocatorsPass'),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => -50, 'name' => ServiceValueResolver::class])

        ->set('argument_resolver.default', DefaultValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => -100, 'name' => DefaultValueResolver::class])

        ->set('argument_resolver.variadic', VariadicValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => -150, 'name' => VariadicValueResolver::class])

        ->set('argument_resolver.query_parameter_value_resolver', QueryParameterValueResolver::class)
            ->tag('controller.targeted_value_resolver', ['name' => QueryParameterValueResolver::class])

        ->set('response_listener', ResponseListener::class)
            ->args([
                param('kernel.charset'),
                abstract_arg('The "set_content_language_from_locale" config value'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('locale_listener', LocaleListener::class)
            ->args([
                service('request_stack'),
                param('kernel.default_locale'),
                service('router')->ignoreOnInvalid(),
                abstract_arg('The "set_locale_from_accept_language" config value'),
                param('kernel.enabled_locales'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('validate_request_listener', ValidateRequestListener::class)
            ->tag('kernel.event_subscriber')

        ->set('disallow_search_engine_index_response_listener', DisallowRobotsIndexingListener::class)
            ->tag('kernel.event_subscriber')

        ->set('error_controller', ErrorController::class)
            ->public()
            ->args([
                service('http_kernel'),
                param('kernel.error_controller'),
                service('error_renderer'),
            ])

        ->set('exception_listener', ErrorListener::class)
            ->args([
                param('kernel.error_controller'),
                service('logger')->nullOnInvalid(),
                param('kernel.debug'),
                abstract_arg('an exceptions to log & status code mapping'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'request'])

        ->set('controller.cache_attribute_listener', CacheAttributeListener::class)
            ->tag('kernel.event_subscriber')

    ;
};
