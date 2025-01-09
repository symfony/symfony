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
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ServiceValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\UidValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver as LegacyBackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DateTimeValueResolver as LegacyDateTimeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver as LegacyDefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver as LegacyQueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver as LegacyRequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver as LegacyRequestPayloadValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver as LegacyRequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver as LegacyServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver as LegacySessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver as LegacyUidValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\DateTimeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver as LegacyVariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerArgumentResolver;
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

        # Deprecated Argument Resolver and Value Resolvers
        ->set('argument_metadata_factory', ArgumentMetadataFactory::class)
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.argument_metadata_factory" instead.')

        ->set('argument_resolver', \Symfony\Component\HttpKernel\Controller\ArgumentResolver::class)
            ->args([
                service('argument_metadata_factory'),
                abstract_arg('argument value resolvers'),
                abstract_arg('targeted value resolvers'),
            ])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller" instead.')

        ->set('argument_resolver.backed_enum_resolver', LegacyBackedEnumValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => LegacyBackedEnumValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.backed_enum" instead.')

        ->set('argument_resolver.uid', LegacyUidValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => LegacyUidValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.uid" instead.')

        ->set('argument_resolver.datetime', LegacyDateTimeValueResolver::class)
            ->args([
                service('clock')->nullOnInvalid(),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => LegacyDateTimeValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.datetime" instead.')

        ->set('argument_resolver.request_payload', LegacyRequestPayloadValueResolver::class)
            ->args([
                service('serializer'),
                service('validator')->nullOnInvalid(),
                service('translator')->nullOnInvalid(),
                param('validator.translation_domain'),
            ])
            ->tag('controller.targeted_value_resolver', ['name' => LegacyRequestPayloadValueResolver::class])
            ->tag('kernel.event_subscriber')
            ->lazy()

        ->set('argument_resolver.request_attribute', LegacyRequestAttributeValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => LegacyRequestAttributeValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.request_attribute" instead.')

        ->set('argument_resolver.request', LegacyRequestValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => LegacyRequestValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.request" instead.')

        ->set('argument_resolver.session', LegacySessionValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 50, 'name' => LegacySessionValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.session" instead.')

        ->set('argument_resolver.service', LegacyServiceValueResolver::class)
            ->args([
                abstract_arg('service locator, set in RegisterControllerArgumentLocatorsPass'),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => -50, 'name' => LegacyServiceValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.service" instead.')

        ->set('argument_resolver.default', LegacyDefaultValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => -100, 'name' => LegacyDefaultValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.default" instead.')

        ->set('argument_resolver.variadic', LegacyVariadicValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => -150, 'name' => LegacyVariadicValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.variadic" instead.')

        ->set('argument_resolver.query_parameter_value_resolver', LegacyQueryParameterValueResolver::class)
            ->tag('controller.targeted_value_resolver', ['name' => LegacyQueryParameterValueResolver::class])
            ->deprecate('symfony/framework-bundle', '7.3', 'The "%service_id%" service is deprecated, use "argument_resolver.controller.query_parameter" instead.')

        # Controller Argument Resolver and Value Resolvers
        ->set('controller.argument_resolver', ControllerArgumentResolver::class)
            ->args([
                service('argument_resolver.argument_metadata_factory'),
                abstract_arg('argument value resolvers'),
                abstract_arg('targeted value resolvers'),
            ])

        /*
        ->set('argument_resolver.controller.backed_enum', BackedEnumValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 100, 'name' => BackedEnumValueResolver::class])

        ->set('argument_resolver.controller.uid', UidValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 100, 'name' => UidValueResolver::class])

        ->set('argument_resolver.controller.datetime', DateTimeValueResolver::class)
            ->args([
                service('clock')->nullOnInvalid(),
            ])
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 100, 'name' => DateTimeValueResolver::class])

        ->set('argument_resolver.controller.request_payload', RequestPayloadValueResolver::class)
            ->args([
                service('serializer'),
                service('validator')->nullOnInvalid(),
                service('translator')->nullOnInvalid(),
                param('validator.translation_domain'),
            ])
            ->tag('controller.targeted_value_resolver', ['name' => RequestPayloadValueResolver::class])
            ->tag('kernel.event_subscriber')
            ->lazy()

        ->set('argument_resolver.controller.request_attribute', RequestAttributeValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 100, 'name' => RequestAttributeValueResolver::class])

        ->set('argument_resolver.controller.request', RequestValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 50, 'name' => RequestValueResolver::class])

        ->set('argument_resolver.controller.session', SessionValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => 50, 'name' => SessionValueResolver::class])

        ->set('argument_resolver.controller.service', ServiceValueResolver::class)
            ->args([
                abstract_arg('service locator, set in RegisterControllerArgumentLocatorsPass'),
            ])
            ->tag('argument_resolver.controller.value_resolver', ['priority' => -50, 'name' => ServiceValueResolver::class])

        ->set('argument_resolver.controller.default', DefaultValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => -100, 'name' => DefaultValueResolver::class])

        ->set('argument_resolver.controller.variadic', VariadicValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => -150, 'name' => VariadicValueResolver::class])

        ->set('argument_resolver.controller.query_parameter_value_resolver', QueryParameterValueResolver::class)
            ->tag('controller.targeted_value_resolver', ['name' => QueryParameterValueResolver::class])
        */

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
