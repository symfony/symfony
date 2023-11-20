CHANGELOG
=========

7.0
---

 * Remove command `translation:update`, use `translation:extract` instead
 * Make the `http_method_override` config option default to `false`
 * Remove `AbstractController::renderForm()`, use `render()` instead
 * Remove the `Symfony\Component\Serializer\Normalizer\ObjectNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` autowiring aliases, type-hint against
   `Symfony\Component\Serializer\Normalizer\NormalizerInterface` or implement `NormalizerAwareInterface` instead
 * Remove the `Http\Client\HttpClient` service, use `Psr\Http\Client\ClientInterface` instead
 * Remove the integration of Doctrine annotations, use native attributes instead
 * Remove `EnableLoggerDebugModePass`, use argument `$debug` of HttpKernel's `Logger` instead
 * Remove `AddDebugLogProcessorPass::configureLogger()`, use HttpKernel's `DebugLoggerConfigurator` instead
 * Make the `framework.handle_all_throwables` config option default to `true`
 * Make the `framework.php_errors.log` config option default to `true`
 * Make the `framework.session.cookie_secure` config option default to `auto`
 * Make the `framework.session.cookie_samesite` config option default to `lax`
 * Make the `framework.session.handler_id` default to null if `save_path` is not set and to `session.handler.native_file` otherwise
 * Make the `framework.uid.default_uuid_version` config option default to `7`
 * Make the `framework.uid.time_based_uuid_version` config option default to `7`
 * Make the `framework.validation.email_validation_mode` config option default to `html5`
 * Remove the `framework.validation.enable_annotations` config option, use `framework.validation.enable_attributes` instead
 * Remove the `framework.serializer.enable_annotations` config option, use `framework.serializer.enable_attributes` instead
 * Remove the `routing.loader.annotation` service, use the `routing.loader.attribute` service instead
 * Remove the `routing.loader.annotation.directory` service, use the `routing.loader.attribute.directory` service instead
 * Remove the `routing.loader.annotation.file` service, use the `routing.loader.attribute.file` service instead
 * Remove `AnnotatedRouteControllerLoader`, use `AttributeRouteControllerLoader` instead
 * Remove `AddExpressionLanguageProvidersPass`, use `Symfony\Component\Routing\DependencyInjection\AddExpressionLanguageProvidersPass` instead
 * Remove `DataCollectorTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\DataCollectorTranslatorPass` instead
 * Remove `LoggingTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\LoggingTranslatorPass` instead
 * Remove `WorkflowGuardListenerPass`, use `Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass` instead

6.4
---

 * Add `HttpClientAssertionsTrait`
 * Add `AbstractController::renderBlock()` and `renderBlockView()`
 * Add native return type to `Translator` and to `Application::reset()`
 * Deprecate the integration of Doctrine annotations, either uninstall the `doctrine/annotations` package or disable the integration by setting `framework.annotations` to `false`
 * Enable `json_decode_detailed_errors` context for Serializer by default if `kernel.debug` is true and the `seld/jsonlint` package is installed
 * Add `DomCrawlerAssertionsTrait::assertAnySelectorTextContains(string $selector, string $text)`
 * Add `DomCrawlerAssertionsTrait::assertAnySelectorTextSame(string $selector, string $text)`
 * Add `DomCrawlerAssertionsTrait::assertAnySelectorTextNotContains(string $selector, string $text)`
 * Deprecate `EnableLoggerDebugModePass`, use argument `$debug` of HttpKernel's `Logger` instead
 * Deprecate `AddDebugLogProcessorPass::configureLogger()`, use HttpKernel's `DebugLoggerConfigurator` instead
 * Deprecate not setting the `framework.handle_all_throwables` config option; it will default to `true` in 7.0
 * Deprecate not setting the `framework.php_errors.log` config option; it will default to `true` in 7.0
 * Deprecate not setting the `framework.session.cookie_secure` config option; it will default to `auto` in 7.0
 * Deprecate not setting the `framework.session.cookie_samesite` config option; it will default to `lax` in 7.0
 * Deprecate not setting either `framework.session.handler_id` or `save_path` config options; `handler_id` will
   default to null in 7.0 if `save_path` is not set and to `session.handler.native_file` otherwise
 * Deprecate not setting the `framework.uid.default_uuid_version` config option; it will default to `7` in 7.0
 * Deprecate not setting the `framework.uid.time_based_uuid_version` config option; it will default to `7` in 7.0
 * Deprecate not setting the `framework.validation.email_validation_mode` config option; it will default to `html5` in 7.0
 * Deprecate `framework.validation.enable_annotations`, use `framework.validation.enable_attributes` instead
 * Deprecate `framework.serializer.enable_annotations`, use `framework.serializer.enable_attributes` instead
 * Add `array $tokenAttributes = []` optional parameter to `KernelBrowser::loginUser()`
 * Add support for relative URLs in BrowserKit's redirect assertion
 * Change BrowserKitAssertionsTrait::getClient() to be protected
 * Deprecate the `framework.asset_mapper.provider` config option
 * Add `--exclude` option to the `cache:pool:clear` command
 * Add parameters deprecations to the output of `debug:container` command
 * Change `framework.asset_mapper.importmap_polyfill` from a URL to the name of an item in the importmap
 * Provide `$buildDir` when running `CacheWarmer` to build read-only resources
 * Add the global `--profile` option to the console to enable profiling commands
 * Deprecate the `routing.loader.annotation` service, use the `routing.loader.attribute` service instead
 * Deprecate the `routing.loader.annotation.directory` service, use the `routing.loader.attribute.directory` service instead
 * Deprecate the `routing.loader.annotation.file` service, use the `routing.loader.attribute.file` service instead
 * Deprecate `AnnotatedRouteControllerLoader`, use `AttributeRouteControllerLoader` instead
 * Deprecate `AddExpressionLanguageProvidersPass`, use `Symfony\Component\Routing\DependencyInjection\AddExpressionLanguageProvidersPass` instead
 * Deprecate `DataCollectorTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\DataCollectorTranslatorPass` instead
 * Deprecate `LoggingTranslatorPass`, use `Symfony\Component\Translation\DependencyInjection\LoggingTranslatorPass` instead
 * Deprecate `WorkflowGuardListenerPass`, use `Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass` instead

6.3
---

 * Add `extra` option for `http_client.default_options` and `http_client.scoped_client`
 * Add `DomCrawlerAssertionsTrait::assertSelectorCount(int $count, string $selector)`
 * Allow to avoid `limit` definition in a RateLimiter configuration when using the `no_limit` policy
 * Add `--format` option to the `debug:config` command
 * Add support to pass namespace wildcard in `framework.messenger.routing`
 * Deprecate `framework:exceptions` tag, unwrap it and replace `framework:exception` tags' `name` attribute by `class`
 * Deprecate the `notifier.logger_notification_listener` service, use the `notifier.notification_logger_listener` service instead
 * Allow setting private services with the test container
 * Register alias for argument for workflow services with workflow name only
 * Configure the `ErrorHandler` on `FrameworkBundle::boot()`
 * Allow setting `debug.container.dump` to `false` to disable dumping the container to XML
 * Add `framework.http_cache.skip_response_headers` option
 * Display warmers duration on debug verbosity for `cache:clear` command
 * Add `AbstractController::sendEarlyHints()` to send HTTP Early Hints
 * Add autowiring aliases for `Http\Client\HttpAsyncClient`
 * Deprecate the `Http\Client\HttpClient` service, use `Psr\Http\Client\ClientInterface` instead
 * Add `stop_worker_on_signals` configuration option to `messenger` to define signals which would stop a worker
 * Add support for `--all` option to clear all cache pools with `cache:pool:clear` command
 * Add `--show-aliases` option to `debug:router` command

6.2
---

 * Add `resolve-env` option to `debug:config` command to display actual values of environment variables in dumped configuration
 * Add `NotificationAssertionsTrait`
 * Add option `framework.handle_all_throwables` to allow `Symfony\Component\HttpKernel\HttpKernel` to handle all kinds of `Throwable`
 * Make `AbstractController::render()` able to deal with forms and deprecate `renderForm()`
 * Deprecate the `Symfony\Component\Serializer\Normalizer\ObjectNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` autowiring aliases, type-hint against
   `Symfony\Component\Serializer\Normalizer\NormalizerInterface` or implement `NormalizerAwareInterface` instead
 * Add service usages list to the `debug:container` command output
 * Add service and alias deprecation message to `debug:container [<name>]` output
 * Tag all workflows services with `workflow`, those with type=workflow are
   tagged with `workflow.workflow`, and those with type=state_machine with
   `workflow.state_machine`
 * Add `rate_limiter` configuration option to `messenger.transport` to allow rate limited transports using the RateLimiter component
 * Remove `@internal` tag from secret vaults to allow them to be used directly outside the framework bundle and custom vaults to be added
 * Deprecate `framework.form.legacy_error_messages` config node
 * Add a `framework.router.cache_dir` configuration option to configure the default `Router` `cache_dir` option
 * Add option `framework.messenger.buses.*.default_middleware.allow_no_senders` to enable throwing when a message doesn't have a sender
 * Deprecate `AbstractController::renderForm()`, use `render()` instead
 * Deprecate `FrameworkExtension::registerRateLimiter()`

6.1
---

 * Add support for configuring semaphores
 * Environment variable `SYMFONY_IDE` is read by default when `framework.ide` config is not set
 * Load PHP configuration files by default in the `MicroKernelTrait`
 * Add `cache:pool:invalidate-tags` command
 * Add `xliff` support in addition to `xlf` for `XliffFileDumper`
 * Deprecate the `reset_on_message` config option. It can be set to `true` only and does nothing now
 * Add `trust_x_sendfile_type_header` option
 * Add support for first-class callable route controller in `MicroKernelTrait`
 * Add tag `routing.condition_service` to autoconfigure routing condition services
 * Automatically register kernel methods marked with the `Symfony\Component\Routing\Annotation\Route` attribute or annotation as controllers in `MicroKernelTrait`
 * Deprecate not setting the `http_method_override` config option. The default value will change to `false` in 7.0.
 * Add `framework.profiler.collect_serializer_data` config option, set it to `true` to enable the serializer data collector and profiler panel

6.0
---

 * Remove the `session.storage` alias and `session.storage.*` services, use the `session.storage.factory` alias and `session.storage.factory.*` services instead
 * Remove `framework.session.storage_id` configuration option, use the `framework.session.storage_factory_id` configuration option instead
 * Remove the `session` service and the `SessionInterface` alias, use the `\Symfony\Component\HttpFoundation\Request::getSession()` or the new `\Symfony\Component\HttpFoundation\RequestStack::getSession()` methods instead
 * Remove the `session.attribute_bag` service and `session.flash_bag` service
 * Remove the `lock.RESOURCE_NAME` and `lock.RESOURCE_NAME.store` services and the `lock`, `LockInterface`, `lock.store` and `PersistingStoreInterface` aliases, use `lock.RESOURCE_NAME.factory`, `lock.factory` or `LockFactory` instead
 * The `form.factory`, `form.type.file`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services are now private
 * Remove the `output-format` and `xliff-version` options from `TranslationUpdateCommand`
 * Remove `has()`, `get()`, `getDoctrine()`n and `dispatchMessage()` from `AbstractController`, use method/constructor injection instead
 * Make the "framework.router.utf8" configuration option default to `true`
 * Remove the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Make the `profiler` service private
 * Remove all other values than "none", "php_array" and "file" for `framework.annotation.cache`
 * Register workflow services as private
 * Remove support for passing a `RouteCollectionBuilder` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead
 * Remove the `cache.adapter.doctrine` service
 * Remove the `framework.translator.enabled_locales` config option, use `framework.enabled_locales` instead
 * Make the `framework.messenger.reset_on_message` configuration option default to `true`

5.4
---

 * Add `set_locale_from_accept_language` config option to automatically set the request locale based on the `Accept-Language`
   HTTP request header and the `framework.enabled_locales` config option
 * Add `set_content_language_from_locale` config option to automatically set the `Content-Language` HTTP response header based on the Request locale
 * Deprecate the `framework.translator.enabled_locales`, use `framework.enabled_locales` instead
 * Add autowiring alias for `HttpCache\StoreInterface`
 * Add the ability to enable the profiler using a request query parameter, body parameter or attribute
 * Deprecate the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Deprecate the public `profiler` service to private
 * Deprecate `get()`, `has()`, `getDoctrine()`, and `dispatchMessage()` in `AbstractController`, use method/constructor injection instead
 * Deprecate the `cache.adapter.doctrine` service
 * Add support for resetting container services after each messenger message
 * Add `configureContainer()`, `configureRoutes()`, `getConfigDir()` and `getBundlesPath()` to `MicroKernelTrait`
 * Add support for configuring log level, and status code by exception class
 * Bind the `default_context` parameter onto serializer's encoders and normalizers
 * Add support for `statusCode` default parameter when loading a template directly from route using the `Symfony\Bundle\FrameworkBundle\Controller\TemplateController` controller
 * Deprecate `translation:update` command, use `translation:extract` instead
 * Add `PhpStanExtractor` support for the PropertyInfo component
 * Add `cache.adapter.doctrine_dbal` service to replace `cache.adapter.pdo` when a Doctrine DBAL connection is used.

5.3
---

 * Deprecate the `session.storage` alias and `session.storage.*` services, use the `session.storage.factory` alias and `session.storage.factory.*` services instead
 * Deprecate the `framework.session.storage_id` configuration option, use the `framework.session.storage_factory_id` configuration option instead
 * Deprecate the `session` service and the `SessionInterface` alias, use the `Request::getSession()` or the new `RequestStack::getSession()` methods instead
 * Add `AbstractController::renderForm()` to render a form and set the appropriate HTTP status code
 * Add support for configuring PHP error level to log levels
 * Add the `dispatcher` option to `debug:event-dispatcher`
 * Add the `event_dispatcher.dispatcher` tag
 * Add `assertResponseFormatSame()` in `BrowserKitAssertionsTrait`
 * Add support for configuring UUID factory services
 * Add tag `assets.package` to register asset packages
 * Add support to use a PSR-6 compatible cache for Doctrine annotations
 * Deprecate all other values than "none", "php_array" and "file" for `framework.annotation.cache`
 * Add `KernelTestCase::getContainer()` as the best way to get a container in tests
 * Rename the container parameter `profiler_listener.only_master_requests` to `profiler_listener.only_main_requests`
 * Add service `fragment.uri_generator` to generate the URI of a fragment
 * Deprecate registering workflow services as public
 * Deprecate option `--xliff-version` of the `translation:update` command, use e.g. `--format=xlf20` instead
 * Deprecate option `--output-format` of the `translation:update` command, use e.g. `--format=xlf20` instead

5.2.0
-----

 * Added `framework.http_cache` configuration tree
 * Added `framework.trusted_proxies` and `framework.trusted_headers` configuration options
 * Deprecated the public `form.factory`, `form.type.file`, `translator`, `security.csrf.token_manager`, `serializer`,
   `cache_clearer`, `filesystem` and `validator` services to private.
 * Added `TemplateAwareDataCollectorInterface` and `AbstractDataCollector` to simplify custom data collector creation and leverage autoconfiguration
 * Add `cache.adapter.redis_tag_aware` tag to use `RedisCacheAwareAdapter`
 * added `framework.http_client.retry_failing` configuration tree
 * added `assertCheckboxChecked()` and `assertCheckboxNotChecked()` in `WebTestCase`
 * added `assertFormValue()` and `assertNoFormValue()` in `WebTestCase`
 * Added "--as-tree=3" option to `translation:update` command to dump messages as a tree-like structure. The given value defines the level where to switch to inline YAML
 * Deprecated the `lock.RESOURCE_NAME` and `lock.RESOURCE_NAME.store` services and the `lock`, `LockInterface`, `lock.store` and `PersistingStoreInterface` aliases, use `lock.RESOURCE_NAME.factory`, `lock.factory` or `LockFactory` instead.

5.1.0
-----
 * Removed `--no-backup` option from `translation:update` command (broken since `5.0.0`)
 * Added link to source for controllers registered as named services
 * Added link to source on controller on `router:match`/`debug:router` (when `framework.ide` is configured)
 * Added the `framework.router.default_uri` configuration option to configure the default `RequestContext`
 * Made `MicroKernelTrait::configureContainer()` compatible with `ContainerConfigurator`
 * Added a new `mailer.message_bus` option to configure or disable the message bus to use to send mails.
 * Added flex-compatible default implementation for `MicroKernelTrait::registerBundles()`
 * Deprecated passing a `RouteCollectionBuilder` to `MicroKernelTrait::configureRoutes()`, type-hint `RoutingConfigurator` instead
 * The `TemplateController` now accepts context argument
 * Deprecated *not* setting the "framework.router.utf8" configuration option as it will default to `true` in Symfony 6.0
 * Added tag `routing.expression_language_function` to define functions available in route conditions
 * Added `debug:container --deprecations` option to see compile-time deprecations.
 * Made `BrowserKitAssertionsTrait` report the original error message in case of a failure
 * Added ability for `config:dump-reference` and `debug:config` to dump and debug kernel container extension configuration.
 * Deprecated `session.attribute_bag` service and `session.flash_bag` service.

5.0.0
-----

 * Removed support to load translation resources from the legacy directories `src/Resources/translations/` and `src/Resources/<BundleName>/translations/`
 * Removed `ControllerNameParser`.
 * Removed `ResolveControllerNameSubscriber`
 * Removed support for `bundle:controller:action` to reference controllers. Use `serviceOrFqcn::method` instead
 * Removed support for PHP templating, use Twig instead
 * Removed `Controller`, use `AbstractController` instead
 * Removed `Client`, use `KernelBrowser` instead
 * Removed `ContainerAwareCommand`, use dependency injection instead
 * Removed the `validation.strict_email` option, use `validation.email_validation_mode` instead
 * Removed the `cache.app.simple` service and its corresponding PSR-16 autowiring alias
 * Removed cache-related compiler passes and `RequestDataCollector`
 * Removed the `translator.selector` and `session.save_listener` services
 * Removed `SecurityUserValueResolver`, use `UserValueResolver` instead
 * Removed `routing.loader.service`.
 * Service route loaders must be tagged with `routing.route_loader`.
 * Added `slugger` service and `SluggerInterface` alias
 * Removed the `lock.store.flock`, `lock.store.semaphore`, `lock.store.memcached.abstract` and `lock.store.redis.abstract` services.
 * Removed the `router.cache_class_prefix` parameter.

4.4.0
-----

 * Added `lint:container` command to check that services wiring matches type declarations
 * Added `MailerAssertionsTrait`
 * Deprecated support for `templating` engine in `TemplateController`, use Twig instead
 * Deprecated the `$parser` argument of `ControllerResolver::__construct()` and `DelegatingLoader::__construct()`
 * Deprecated the `controller_name_converter` and `resolve_controller_name_subscriber` services
 * The `ControllerResolver` and `DelegatingLoader` classes have been marked as `final`
 * Added support for configuring chained cache pools
 * Deprecated calling `WebTestCase::createClient()` while a kernel has been booted, ensure the kernel is shut down before calling the method
 * Deprecated `routing.loader.service`, use `routing.loader.container` instead.
 * Not tagging service route loaders with `routing.route_loader` has been deprecated.
 * Overriding the methods `KernelTestCase::tearDown()` and `WebTestCase::tearDown()` without the `void` return-type is deprecated.
 * Added new `error_controller` configuration to handle system exceptions
 * Added sort option for `translation:update` command.
 * [BC Break] The `framework.messenger.routing.senders` config key is not deeply merged anymore.
 * Added `secrets:*` commands to deal with secrets seamlessly.
 * Made `framework.session.handler_id` accept a DSN
 * Marked the `RouterDataCollector` class as `@final`.
 * [BC Break] The `framework.messenger.buses.<name>.middleware` config key is not deeply merged anymore.
 * Moved `MailerAssertionsTrait` in `KernelTestCase`

4.3.0
-----

 * Deprecated the `framework.templating` option, configure the Twig bundle instead.
 * Added `WebTestAssertionsTrait` (included by default in `WebTestCase`)
 * Renamed `Client` to `KernelBrowser`
 * Not passing the project directory to the constructor of the `AssetsInstallCommand` is deprecated. This argument will
   be mandatory in 5.0.
 * Deprecated the "Psr\SimpleCache\CacheInterface" / "cache.app.simple" service, use "Symfony\Contracts\Cache\CacheInterface" / "cache.app" instead
 * Added the ability to specify a custom `serializer` option for each
   transport under`framework.messenger.transports`.
 * Added the `RegisterLocaleAwareServicesPass` and configured the `LocaleAwareListener`
 * [BC Break] When using Messenger, the default transport changed from
   using Symfony's serializer service to use `PhpSerializer`, which uses
   PHP's native `serialize()` and `unserialize()` functions. To use the
   original serialization method, set the `framework.messenger.default_serializer`
   config option to `messenger.transport.symfony_serializer`. Or set the
   `serializer` option under one specific `transport`.
 * [BC Break] The `framework.messenger.serializer` config key changed to
   `framework.messenger.default_serializer`, which holds the string service
   id and `framework.messenger.symfony_serializer`, which configures the
   options if you're using Symfony's serializer.
 * [BC Break] Removed the `framework.messenger.routing.send_and_handle` configuration.
   Instead of setting it to true, configure a `SyncTransport` and route messages to it.
 * Added information about deprecated aliases in `debug:autowiring`
 * Added php ini session options `sid_length` and `sid_bits_per_character`
   to the `session` section of the configuration
 * Added support for Translator paths, Twig paths in translation commands.
 * Added support for PHP files with translations in translation commands.
 * Added support for boolean container parameters within routes.
 * Added the `messenger:setup-transports` command to setup messenger transports
 * Added a `InMemoryTransport` to Messenger. Use it with a DSN starting with `in-memory://`.
 * Added `framework.property_access.throw_exception_on_invalid_property_path` config option.
 * Added `cache:pool:list` command to list all available cache pools.

4.2.0
-----

 * Added a `AbstractController::addLink()` method to add Link headers to the current response
 * Allowed configuring taggable cache pools via a new `framework.cache.pools.tags` option (bool|service-id)
 * Allowed configuring PDO-based cache pools via a new `cache.adapter.pdo` abstract service
 * Deprecated auto-injection of the container in AbstractController instances, register them as service subscribers instead
 * Deprecated processing of services tagged `security.expression_language_provider` in favor of a new `AddExpressionLanguageProvidersPass` in SecurityBundle.
 * Deprecated the `Symfony\Bundle\FrameworkBundle\Controller\Controller` class in favor of `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`.
 * Enabled autoconfiguration for `Psr\Log\LoggerAwareInterface`
 * Added new "auto" mode for `framework.session.cookie_secure` to turn it on when HTTPS is used
 * Removed the `framework.messenger.encoder` and `framework.messenger.decoder` options. Use the `framework.messenger.serializer.id` option to replace the Messenger serializer.
 * Deprecated the `ContainerAwareCommand` class in favor of `Symfony\Component\Console\Command\Command`
 * Made `debug:container` and `debug:autowiring` ignore backslashes in service ids
 * Deprecated the `Templating\Helper\TranslatorHelper::transChoice()` method, use the `trans()` one instead with a `%count%` parameter
 * Deprecated `CacheCollectorPass`. Use `Symfony\Component\Cache\DependencyInjection\CacheCollectorPass` instead.
 * Deprecated `CachePoolClearerPass`. Use `Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass` instead.
 * Deprecated `CachePoolPass`. Use `Symfony\Component\Cache\DependencyInjection\CachePoolPass` instead.
 * Deprecated `CachePoolPrunerPass`. Use `Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass` instead.
 * Deprecated support for legacy translations directories `src/Resources/translations/` and `src/Resources/<BundleName>/translations/`, use `translations/` instead.
 * Deprecated support for the legacy directory structure in `translation:update` and `debug:translation` commands.

4.1.0
-----

 * Allowed to pass an optional `LoggerInterface $logger` instance to the `Router`
 * Added a new `parameter_bag` service with related autowiring aliases to access parameters as-a-service
 * Allowed the `Router` to work with any PSR-11 container
 * Added option in workflow dump command to label graph with a custom label
 * Using a `RouterInterface` that does not implement the `WarmableInterface` is deprecated.
 * Warming up a router in `RouterCacheWarmer` that does not implement the `WarmableInterface` is deprecated and will not
   be supported anymore in 5.0.
 * The `RequestDataCollector` class has been deprecated. Use the `Symfony\Component\HttpKernel\DataCollector\RequestDataCollector` class instead.
 * The `RedirectController` class allows for 307/308 HTTP status codes
 * Deprecated `bundle:controller:action` syntax to reference controllers. Use `serviceOrFqcn::method` instead where `serviceOrFqcn`
   is either the service ID or the FQCN of the controller.
 * Deprecated `Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser`
 * The `container.service_locator` tag of `ServiceLocator`s is now autoconfigured.
 * Add the ability to search a route in `debug:router`.
 * Add the ability to use SameSite cookies for sessions.

4.0.0
-----

 * The default `type` option of the `framework.workflows.*` configuration entries is `state_machine`
 * removed `AddConsoleCommandPass`, `AddConstraintValidatorsPass`,
   `AddValidatorInitializersPass`, `CompilerDebugDumpPass`,  `ConfigCachePass`,
   `ControllerArgumentValueResolverPass`, `FormPass`, `PropertyInfoPass`,
   `RoutingResolverPass`, `SerializerPass`, `ValidateWorkflowsPass`
 * made  `Translator::__construct()` `$defaultLocale` argument required
 * removed `SessionListener`, `TestSessionListener`
 * Removed `cache:clear` warmup part along with the `--no-optional-warmers` option
 * Removed core form types services registration when unnecessary
 * Removed `framework.serializer.cache` option and `serializer.mapping.cache.apc`, `serializer.mapping.cache.doctrine.apc` services
 * Removed `ConstraintValidatorFactory`
 * Removed class parameters related to routing
 * Removed absolute template paths support in the template name parser
 * Removed support of the `KERNEL_DIR` environment variable with `KernelTestCase::getKernelClass()`.
 * Removed the `KernelTestCase::getPhpUnitXmlDir()` and `KernelTestCase::getPhpUnitCliConfigArgument()` methods.
 * Removed the "framework.validation.cache" configuration option. Configure the "cache.validator" service under "framework.cache.pools" instead.
 * Removed `PhpStringTokenParser`, use `Symfony\Component\Translation\Extractor\PhpStringTokenParser` instead.
 * Removed `PhpExtractor`, use `Symfony\Component\Translation\Extractor\PhpExtractor` instead.
 * Removed the `use_strict_mode` session option, it's is now enabled by default

3.4.0
-----

 * Added `translator.default_path` option and parameter
 * Session `use_strict_mode` is now enabled by default and the corresponding option has been deprecated
 * Made the `cache:clear` command to *not* clear "app" PSR-6 cache pools anymore,
   but to still clear "system" ones; use the `cache:pool:clear` command to clear "app" pools instead
 * Always register a minimalist logger that writes in `stderr`
 * Deprecated `profiler.matcher` option
 * Added support for `EventSubscriberInterface` on `MicroKernelTrait`
 * Removed `doctrine/cache` from the list of required dependencies in `composer.json`
 * Deprecated `validator.mapping.cache.doctrine.apc` service
 * The `symfony/stopwatch` dependency has been removed, require it via `composer
   require symfony/stopwatch` in your `dev` environment.
 * Deprecated using the `KERNEL_DIR` environment variable with `KernelTestCase::getKernelClass()`.
 * Deprecated the `KernelTestCase::getPhpUnitXmlDir()` and `KernelTestCase::getPhpUnitCliConfigArgument()` methods.
 * Deprecated `AddCacheClearerPass`, use tagged iterator arguments instead.
 * Deprecated `AddCacheWarmerPass`, use tagged iterator arguments instead.
 * Deprecated `TranslationDumperPass`, use
   `Symfony\Component\Translation\DependencyInjection\TranslationDumperPass` instead
 * Deprecated `TranslationExtractorPass`, use
   `Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass` instead
 * Deprecated `TranslatorPass`, use
   `Symfony\Component\Translation\DependencyInjection\TranslatorPass` instead
 * Added `command` attribute to the `console.command` tag which takes the command
   name as value, using it makes the command lazy
 * Added `cache:pool:prune` command to allow manual stale cache item pruning of supported PSR-6 and PSR-16 cache pool
   implementations
 * Deprecated `Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader`, use
   `Symfony\Component\Translation\Reader\TranslationReader` instead
 * Deprecated `translation.loader` service, use `translation.reader` instead
 * `AssetsInstallCommand::__construct()` now takes an instance of
   `Symfony\Component\Filesystem\Filesystem` as first argument
 * `CacheClearCommand::__construct()` now takes an instance of
   `Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface` as
    first argument
 * `CachePoolClearCommand::__construct()` now takes an instance of
   `Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer` as
    first argument
 * `EventDispatcherDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\EventDispatcher\EventDispatcherInterface` as
    first argument
 * `RouterDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument
 * `RouterMatchCommand::__construct()` now takes an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument
 * `TranslationDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument
 * `TranslationUpdateCommand::__construct()` now takes an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument
 * `AssetsInstallCommand`, `CacheClearCommand`, `CachePoolClearCommand`,
   `EventDispatcherDebugCommand`, `RouterDebugCommand`, `RouterMatchCommand`,
   `TranslationDebugCommand`, `TranslationUpdateCommand`, `XliffLintCommand`
    and `YamlLintCommand` classes have been marked as final
 * Added `asset.request_context.base_path` and `asset.request_context.secure` parameters
   to provide a default request context in case the stack is empty (similar to `router.request_context.*` parameters)
 * Display environment variables managed by `Dotenv` in `AboutCommand`

3.3.0
-----

 * Not defining the `type` option of the `framework.workflows.*` configuration entries is deprecated.
   The default value will be `state_machine` in Symfony 4.0.
 * Deprecated the `CompilerDebugDumpPass` class
 * Deprecated the "framework.trusted_proxies" configuration option and the corresponding "kernel.trusted_proxies" parameter
 * Added a new version strategy option called "json_manifest_path"
   that allows you to use the `JsonManifestVersionStrategy`.
 * Added `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`. It provides
   the same helpers as the `Controller` class, but does not allow accessing the dependency
   injection container, in order to encourage explicit dependency declarations.
 * Added support for the `controller.service_arguments` tag, for injecting services into controllers' actions
 * Changed default configuration for
   assets/forms/validation/translation/serialization/csrf from `canBeEnabled()` to
   `canBeDisabled()` when Flex is used
 * The server:* commands and their associated router files were moved to WebServerBundle
 * Translation related services are not loaded anymore when the `framework.translator` option
   is disabled.
 * Added `GlobalVariables::getToken()`
 * Deprecated `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConsoleCommandPass`. Use `Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass` instead.
 * Added configurable paths for validation files
 * Deprecated `SerializerPass`, use `Symfony\Component\Serializer\DependencyInjection\SerializerPass` instead
 * Deprecated `FormPass`, use `Symfony\Component\Form\DependencyInjection\FormPass` instead
 * Deprecated `SessionListener`
 * Deprecated `TestSessionListener`
 * Deprecated `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConfigCachePass`.
   Use tagged iterator arguments instead.
 * Deprecated `PropertyInfoPass`, use `Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass` instead
 * Deprecated `ControllerArgumentValueResolverPass`. Use
   `Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass` instead
 * Deprecated `RoutingResolverPass`, use `Symfony\Component\Routing\DependencyInjection\RoutingResolverPass` instead
 * [BC BREAK] The `server:run`, `server:start`, `server:stop` and
   `server:status` console commands have been moved to a dedicated bundle.
   Require `symfony/web-server-bundle` in your composer.json and register
   `Symfony\Bundle\WebServerBundle\WebServerBundle` in your AppKernel to use them.
 * Added `$defaultLocale` as 3rd argument of `Translator::__construct()`
   making `Translator` works with any PSR-11 container
 * Added `framework.serializer.mapping` config option allowing to define custom
   serialization mapping files and directories
 * Deprecated `AddValidatorInitializersPass`, use
   `Symfony\Component\Validator\DependencyInjection\AddValidatorInitializersPass` instead
 * Deprecated `AddConstraintValidatorsPass`, use
   `Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass` instead
 * Deprecated `ValidateWorkflowsPass`, use
   `Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass` instead
 * Deprecated `ConstraintValidatorFactory`, use
   `Symfony\Component\Validator\ContainerConstraintValidatorFactory` instead.
 * Deprecated `PhpStringTokenParser`, use
   `Symfony\Component\Translation\Extractor\PhpStringTokenParser` instead.
 * Deprecated `PhpExtractor`, use
   `Symfony\Component\Translation\Extractor\PhpExtractor` instead.

3.2.0
-----

 * Removed `doctrine/annotations` from the list of required dependencies in `composer.json`
 * Removed `symfony/security-core` and `symfony/security-csrf` from the list of required dependencies in `composer.json`
 * Removed `symfony/templating` from the list of required dependencies in `composer.json`
 * Removed `symfony/translation` from the list of required dependencies in `composer.json`
 * Removed `symfony/asset` from the list of required dependencies in `composer.json`
 * The `Resources/public/images/*` files have been removed.
 * The `Resources/public/css/*.css` files have been removed (they are now inlined in TwigBundle).
 * Added possibility to prioritize form type extensions with `'priority'` attribute on tags `form.type_extension`

3.1.0
-----

 * Added `Controller::json` to simplify creating JSON responses when using the Serializer component
 * Deprecated absolute template paths support in the template name parser
 * Deprecated using core form types without dependencies as services
 * Added `Symfony\Component\HttpHernel\DataCollector\RequestDataCollector::onKernelResponse()`
 * Added `Symfony\Bundle\FrameworkBundle\DataCollector\RequestDataCollector`
 * The `framework.serializer.cache` option and the service `serializer.mapping.cache.apc` have been
   deprecated. APCu should now be automatically used when available.

3.0.0
-----

 * removed `validator.api` parameter
 * removed `alias` option of the `form.type` tag

2.8.0
-----

 * Deprecated the `alias` option of the `form.type_extension` tag in favor of the
   `extended_type`/`extended-type` option
 * Deprecated the `alias` option of the `form.type` tag
 * Deprecated the Shell

2.7.0
-----

 * Added possibility to extract translation messages from a file or files besides extracting from a directory
 * Added `TranslationsCacheWarmer` to create catalogues at warmup

2.6.0
-----

 * Added helper commands (`server:start`, `server:stop` and `server:status`) to control the built-in web
   server in the background
 * Added `Controller::isCsrfTokenValid` helper
 * Added configuration for the PropertyAccess component
 * Added `Controller::redirectToRoute` helper
 * Added `Controller::addFlash` helper
 * Added `Controller::isGranted` helper
 * Added `Controller::denyAccessUnlessGranted` helper
 * Deprecated `app.security` in twig as `app.user` and `is_granted()` are already available

2.5.0
-----

 * Added `translation:debug` command
 * Added `--no-backup` option to `translation:update` command
 * Added `config:debug` command
 * Added `yaml:lint` command
 * Deprecated the `RouterApacheDumperCommand` which will be removed in Symfony 3.0.

2.4.0
-----

 * allowed multiple IP addresses in profiler matcher settings
 * added stopwatch helper to time templates with the WebProfilerBundle
 * added service definition for "security.secure_random" service
 * added service definitions for the new Security CSRF sub-component

2.3.0
-----

 * [BC BREAK] added a way to disable the profiler (when disabling the profiler, it is now completely removed)
   To get the same "disabled" behavior as before, set `enabled` to `true` and `collect` to `false`
 * [BC BREAK] the `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass` was moved
   to `Component\HttpKernel\DependencyInjection\RegisterListenersPass`
 * added ControllerNameParser::build() which converts a controller short notation (a:b:c) to a class::method notation
 * added possibility to run PHP built-in server in production environment
 * added possibility to load the serializer component in the service container
 * added route debug information when using the `router:match` command
 * added `TimedPhpEngine`
 * added `--clean` option to the `translation:update` command
 * added `http_method_override` option
 * added support for default templates per render tag
 * added FormHelper::form(), FormHelper::start() and FormHelper::end()
 * deprecated FormHelper::enctype() in favor of FormHelper::start()
 * RedirectController actions now receive the Request instance via the method signature.

2.2.0
-----

 * added a new `uri_signer` service to help sign URIs
 * deprecated `Symfony\Bundle\FrameworkBundle\HttpKernel::render()` and `Symfony\Bundle\FrameworkBundle\HttpKernel::forward()`
 * deprecated the `Symfony\Bundle\FrameworkBundle\HttpKernel` class in favor of `Symfony\Component\HttpKernel\DependencyInjection\ContainerAwareHttpKernel`
 * added support for adding new HTTP content rendering strategies (like ESI and Hinclude)
   in the DIC via the `kernel.fragment_renderer` tag
 * [BC BREAK] restricted the `Symfony\Bundle\FrameworkBundle\HttpKernel::render()` method to only accept URIs or ControllerReference instances
   * `Symfony\Bundle\FrameworkBundle\HttpKernel::render()` method signature changed and the first argument
     must now be a URI or a ControllerReference instance (the `generateInternalUri()` method was removed)
   * The internal routes (`Resources/config/routing/internal.xml`) have been removed and replaced with a listener (`Symfony\Component\HttpKernel\EventListener\FragmentListener`)
   * The `render` method of the `actions` templating helper signature and arguments changed
 * replaced Symfony\Bundle\FrameworkBundle\Controller\TraceableControllerResolver by Symfony\Component\HttpKernel\Controller\TraceableControllerResolver
 * replaced Symfony\Component\HttpKernel\Debug\ContainerAwareTraceableEventDispatcher by Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher
 * added Client::enableProfiler()
 * a new parameter has been added to the DIC: `router.request_context.base_url`
   You can customize it for your functional tests or for generating URLs with
   the right base URL when your are in the CLI context.
 * added support for default templates per render tag

2.1.0
-----

 * moved the translation files to the Form and Validator components
 * changed the default extension for XLIFF files from .xliff to .xlf
 * moved Symfony\Bundle\FrameworkBundle\ContainerAwareEventDispatcher to Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
 * moved Symfony\Bundle\FrameworkBundle\Debug\TraceableEventDispatcher to Symfony\Component\EventDispatcher\ContainerAwareTraceableEventDispatcher
 * added a router:match command
 * added a config:dump-reference command
 * added a server:run command
 * added kernel.event_subscriber tag
 * added a way to create relative symlinks when running assets:install command (--relative option)
 * added Controller::getUser()
 * [BC BREAK] assets_base_urls and base_urls merging strategy has changed
 * changed the default profiler storage to use the filesystem instead of SQLite
 * added support for placeholders in route defaults and requirements (replaced
   by the value set in the service container)
 * added Filesystem component as a dependency
 * added support for hinclude (use ``standalone: 'js'`` in render tag)
 * session options: lifetime, path, domain, secure, httponly were deprecated.
   Prefixed versions should now be used instead: cookie_lifetime, cookie_path,
   cookie_domain, cookie_secure, cookie_httponly
 * [BC BREAK] following session options: 'lifetime', 'path', 'domain', 'secure',
   'httponly' are now prefixed with cookie_ when dumped to the container
 * Added `handler_id` configuration under `session` key to represent `session.handler`
   service, defaults to `session.handler.native_file`.
 * Added `gc_maxlifetime`, `gc_probability`, and `gc_divisor` to session
   configuration. This means session garbage collection has a
  `gc_probability`/`gc_divisor` chance of being run. The `gc_maxlifetime` defines
   how long a session can idle for. It is different from cookie lifetime which
   declares how long a cookie can be stored on the remote client.
 * Removed 'auto_start' configuration parameter from session config. The session will
   start on demand.
 * [BC BREAK] TemplateNameParser::parseFromFilename() has been moved to a dedicated
   parser: TemplateFilenameParser::parse().
 * [BC BREAK] Kernel parameters are replaced by their value wherever they appear
   in Route patterns, requirements and defaults. Use '%%' as the escaped value for '%'.
 * [BC BREAK] Switched behavior of flash messages to expire flash messages on retrieval
   using Symfony\Component\HttpFoundation\Session\Flash\FlashBag as opposed to on
   next pageload regardless of whether they are displayed or not.
