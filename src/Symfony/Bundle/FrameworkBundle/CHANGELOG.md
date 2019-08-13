CHANGELOG
=========

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
