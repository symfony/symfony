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

use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver;
use Symfony\Component\ExpressionLanguage\ConstantFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\Lock;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DateTimeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\LockValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\MapRateLimitValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ServiceValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ErrorController;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\CacheAttributeListener;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\DisallowRobotsIndexingListener;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
use Symfony\Component\HttpKernel\EventListener\IsSignatureValidAttributeListener;
use Symfony\Component\HttpKernel\EventListener\LocaleListener;
use Symfony\Component\HttpKernel\EventListener\LockAttributeListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\SerializeControllerResultAttributeListener;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('controller_resolver', ControllerResolver::class)
            ->args([
                service('service_container'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->call('allowControllers', [[AbstractController::class]])
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
                service('controller.expression_language')->nullOnInvalid(),
            ])
            ->tag('controller.targeted_value_resolver', ['name' => RequestPayloadValueResolver::class])
            ->tag('kernel.event_subscriber')
            ->lazy()

        ->set('argument_resolver.request_attribute', RequestAttributeValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => RequestAttributeValueResolver::class])

        ->set('argument_resolver.request', RequestValueResolver::class)
            // type-hinted Request arguments must not trigger entity-manager bootstrap
            ->tag('controller.argument_value_resolver', ['priority' => 120, 'before' => EntityValueResolver::class, 'name' => RequestValueResolver::class])

        ->set('argument_resolver.session', SessionValueResolver::class)
            // type-hinted Session arguments must not trigger entity-manager bootstrap
            ->tag('controller.argument_value_resolver', ['priority' => 120, 'before' => EntityValueResolver::class, 'name' => SessionValueResolver::class])

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

        ->set('argument_resolver.header_value_resolver', RequestHeaderValueResolver::class)
            ->tag('controller.targeted_value_resolver', ['name' => RequestHeaderValueResolver::class])

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
                abstract_arg('list of loggers by log_channel'),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'request'])

        ->set('kernel.controller_attributes_listener', ControllerAttributesListener::class)
            ->args([
                abstract_arg('attributes with listeners by event'),
                service('controller.expression_language')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')

        ->set('controller.cache_attribute_listener', CacheAttributeListener::class)
            ->args([
                service('controller.expression_language')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('kernel.reset', ['method' => '?reset'])

        ->set('controller.is_signature_valid_attribute_listener', IsSignatureValidAttributeListener::class)
            ->args([
                service('uri_signer'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('lock.attribute_listener', LockAttributeListener::class)
            ->args([
                tagged_locator('lock.factory', 'name'),
                service('request_stack'),
                service('controller.expression_language')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('kernel.reset', ['method' => 'reset'])
            ->tag('container.remove_if_missing', ['service' => 'lock.factory.abstract'])

        ->set('argument_resolver.lock', LockValueResolver::class)
            ->args([
                service('lock.attribute_listener'),
            ])
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => LockValueResolver::class])
            ->tag('kernel.event_subscriber')
            ->tag('container.remove_if_missing', ['service' => 'lock.attribute_listener'])

        ->set('controller.helper', ControllerHelper::class)
            ->tag('container.service_subscriber')

        ->alias(ControllerHelper::class, 'controller.helper')

        ->set('controller.expression_language', ExpressionLanguage::class)
            ->lazy()
            ->args([
                service('cache.controller_expression_language')->nullOnInvalid(),
                class_exists(ConstantFunctionProvider::class) ? [inline_service(ConstantFunctionProvider::class)->args([['*', '*::*']])] : [],
            ])
            ->call('registerProvider', [service('security.expression_language_provider')->ignoreOnInvalid()])
            ->tag('expression_language.compiled', [
                'attributes' => [
                    MapQueryString::class => ['validationGroups'],
                    MapRequestPayload::class => ['validationGroups'],
                    RateLimit::class => ['key'],
                    Lock::class => ['key'],
                ],
                'variables' => ['request', 'args', 'this'],
            ])
            // the expressions of #[Cache] can also read the attributes of the request and the arguments of the controller
            ->tag('expression_language.compiled', [
                'string_expressions' => [
                    Cache::class => ['if', 'lastModified', 'etag'],
                ],
            ])

        ->set('cache.controller_expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('serialize_controller_result_listener', SerializeControllerResultAttributeListener::class)
        ->args([
            service('serializer')->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')

        ->set('argument_resolver.map_rate_limit', MapRateLimitValueResolver::class)
            ->tag('controller.argument_value_resolver', ['priority' => 100, 'name' => MapRateLimitValueResolver::class])
            ->tag('kernel.event_subscriber')

        ->set('rate_limiter.attribute_listener', RateLimitAttributeListener::class)
            ->args([
                tagged_locator('rate_limiter', 'name'),
                service('controller.expression_language')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('container.remove_if_missing', ['service' => 'limiter'])
    ;
};
