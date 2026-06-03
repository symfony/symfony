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

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;
use Symfony\Component\DependencyInjection\Kernel\FileLocator;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\CacheClearer\ChainCacheClearer;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Symfony\Component\HttpKernel\Config\FileLocator as LegacyFileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface as LegacyServicesResetterInterface;
use Symfony\Component\HttpKernel\EventListener\LocaleAwareListener;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\Runner\Symfony\HttpKernelRunner;
use Symfony\Component\Runtime\Runner\Symfony\ResponseRunner;
use Symfony\Component\Runtime\SymfonyRuntime;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('http_kernel', HttpKernel::class)
            ->public()
            ->args([
                service('event_dispatcher'),
                service('controller_resolver'),
                service('request_stack'),
                service('argument_resolver'),
                false,
            ])
            ->tag('container.hot_path')
            ->tag('container.preload', ['class' => HttpKernelRunner::class])
            ->tag('container.preload', ['class' => ResponseRunner::class])
            ->tag('container.preload', ['class' => SymfonyRuntime::class])
        ->alias(HttpKernelInterface::class, 'http_kernel')

        ->set('request_stack', RequestStack::class)
            ->tag('kernel.reset', ['method' => 'resetRequestFormats', 'on_invalid' => 'ignore'])
            ->public()
        ->alias(RequestStack::class, 'request_stack')

        ->set('http_cache', HttpCache::class)
            ->args([
                service('kernel'),
                service('http_cache.store'),
                service('esi')->nullOnInvalid(),
                abstract_arg('options'),
            ])
            ->tag('container.hot_path')

        ->set('http_cache.store', Store::class)
            ->args([
                param('kernel.share_dir').'/http_cache',
            ])
        ->alias(StoreInterface::class, 'http_cache.store')

        ->set('url_helper', UrlHelper::class)
            ->args([
                service('request_stack'),
                service('router')->ignoreOnInvalid(),
            ])
        ->alias(UrlHelper::class, 'url_helper')

        ->set('cache_warmer', CacheWarmerAggregate::class)
            ->public()
            ->args([
                tagged_iterator('kernel.cache_warmer'),
                param('kernel.debug'),
                \sprintf('%s/%sDeprecations.log', param('kernel.build_dir'), param('kernel.container_class')),
            ])
            ->tag('container.no_preload')

        ->set('cache_clearer', ChainCacheClearer::class)
            ->args([
                tagged_iterator('kernel.cache_clearer'),
            ])

        ->alias(KernelInterface::class, 'kernel')

        ->alias(LegacyFileLocator::class, 'file_locator')
            ->deprecate('symfony/framework-bundle', '8.1', 'The "%alias_id%" alias is deprecated, use "'.FileLocator::class.'" instead.')

        ->set('uri_signer', UriSigner::class)
            ->args([
                new Parameter('kernel.secret'),
                '_hash',
                '_expiration',
                service('clock')->nullOnInvalid(),
            ])
            ->lazy()
        ->alias(UriSigner::class, 'uri_signer')

        ->alias(LegacyServicesResetterInterface::class, 'services_resetter')
            ->deprecate('symfony/framework-bundle', '8.1', 'The "%alias_id%" alias is deprecated, use "'.ServicesResetterInterface::class.'" instead.')

        ->set('locale_aware_listener', LocaleAwareListener::class)
            ->args([
                [], // locale aware services
                service('request_stack'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('slugger', AsciiSlugger::class)
            ->args([
                param('kernel.default_locale'),
            ])
            ->tag('kernel.locale_aware')
        ->alias(SluggerInterface::class, 'slugger')

        ->set('container.getenv', \Closure::class)
            ->factory([\Closure::class, 'fromCallable'])
            ->args([
                [service('service_container'), 'getEnv'],
            ])
            ->tag('routing.expression_language_function', ['function' => 'env'])

        ->set('container.get_routing_condition_service', \Closure::class)
            ->public()
            ->factory([\Closure::class, 'fromCallable'])
            ->args([
                [tagged_locator('routing.condition_service', 'alias'), 'get'],
            ])
            ->tag('routing.expression_language_function', ['function' => 'service'])

        // inherit from this service to lazily access env vars
        ->set('container.env', LazyString::class)
            ->abstract()
            ->factory([LazyString::class, 'fromCallable'])
            ->args([
                service('container.getenv'),
            ])

        // register as abstract and excluded, aka not-autowirable types
        ->set(Request::class)->abstract()->tag('container.excluded')
        ->set(Response::class)->abstract()->tag('container.excluded')
        ->set(SessionInterface::class)->abstract()->tag('container.excluded')
    ;
};
