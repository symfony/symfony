UPGRADE FROM 8.0 to 8.1
=======================

Symfony 8.1 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.1/setup/upgrade_minor.html).

If you're upgrading from a version below 8.0, follow the [8.0 upgrade guide](UPGRADE-8.0.md) first.

Console
-------

 * [BC BREAK] Add `object` support to input options and arguments' default by changing the `$default` type to `mixed` in `InputArgument`, `InputOption`, `#[Argument]` and `#[Option]`
 * Add optional `$format` argument to `SymfonyStyle::createProgressBar()`, `SymfonyStyle::progressStart()`, and `SymfonyStyle::progressIterate()` to allow passing a custom `ProgressBar` format string
 * Deprecate passing both `InputArgument::REQUIRED` and `InputArgument::OPTIONAL` modes to `InputArgument` constructor
 * Deprecate passing more than one out of `InputOption::VALUE_NONE`, `InputOption::VALUE_REQUIRED` and `InputOption::VALUE_OPTIONAL` modes to `InputOption` constructor

DependencyInjection
-------------------

 * Deprecate configuring options `alias`, `parent`, `synthetic`, `file`, `arguments`, `properties`, `configurator` or `calls` when using `from_callable`
 * Deprecate default index/priority methods when defining tagged locators/iterators; use the `#[AsTaggedItem]` attribute instead
 * Deprecate named autowiring alias that don't use `#[Target]`
   ```diff
    public function __construct(
   +    #[Target]
        private StorageInterface $imageStorage,
    ) {
   ```

DoctrineBridge
--------------

 * Deprecate setting an `$aliasMap` in `RegisterMappingsPass`. Namespace aliases are no longer supported in Doctrine.

ErrorHandler
------------

 * Add argument `$deprecationsNamespacesMapping` to `DebugClassLoader::enable()` to configure namespace-to-vendor remapping for deprecation checks

Form
----

 * Deprecate passing boolean as the second argument of `ValidatorExtension` and `FormTypeValidatorExtension`'s constructors; pass a `ViolationMapperInterface` instead
 * Add argument `$violationMapper` to `ValidatorExtensionTrait` and `TypeTestCase`'s `getExtensions()` methods

Filesystem
----------

 * Deprecate calling `Filesystem::mirror()` with option `copy_on_windows`, use option `follow_symlinks` instead

FrameworkBundle
---------------

 * Deprecate setting the `framework.profiler.collect_serializer_data` config option
 * Deprecate the `framework.http_cache.terminate_on_cache_hit` config option
 * Deprecate parameters `router.request_context.scheme` and `router.request_context.host`;
   use the `router.request_context.base_url` parameter or the `framework.router.default_uri` config option instead
 * Deprecate setting `framework.http_client.default_options.caching.max_ttl` to `null`, use a positive integer instead
 * Deprecate `senders` nesting level for messenger routing config; use string or a list of strings instead
 * Deprecate registering console commands by overriding `Bundle::registerCommands()`, use the `#[AsCommand]` attribute or the `console.command` service tag instead
 * Deprecate calling `FrameworkExtension::load()` directly without first loading `ServicesBundle`'s extension. Tests that wire up a `ContainerBuilder` by hand should now do:

   ```diff
   +new ServicesBundle()->getContainerExtension()->load([], $container);

    new FrameworkExtension()->load($config, $container);
   ```

   For real kernels, `FrameworkBundle` carries a `#[RequiredBundle(ServicesBundle::class)]` attribute that should be processed already.

HttpClient
----------

 * Deprecate passing `null` as `$maxTtl` to `CachingHttpClient`, pass a positive integer instead

HttpFoundation
--------------

 * Deprecate setting public properties of `Request` and `Response` objects directly; use setters or constructor arguments instead

HttpKernel
----------

 * Deprecate `BundleInterface`, use the one from the DependencyInjection component instead
 * Deprecate `MergeExtensionConfigurationPass`, use the one from the DependencyInjection component instead
 * Deprecate `FileLocator`, use the one from the DependencyInjection component instead
 * Deprecate `ServicesResetter`, `ServicesResetterInterface`, and `ResettableServicePass`, use the ones from the DependencyInjection component instead
 * Deprecate passing a non-flat list of attributes to `Controller::setController()`
 * Deprecate the `Symfony\Component\HttpKernel\DependencyInjection\Extension` class, use the parent `Symfony\Component\DependencyInjection\Extension\Extension` class instead:

   ```diff
   - use Symfony\Component\HttpKernel\DependencyInjection\Extension;
   + use Symfony\Component\DependencyInjection\Extension\Extension;

   class ExampleExtension extends Extension
   {
       // ...
   }
   ```
 * Deprecate passing a `ControllerArgumentsEvent` to the `ViewEvent` constructor; pass a `ControllerArgumentsMetadata` instead
 * Deprecate `Bundle::registerCommands()`, use the `#[AsCommand]` attribute or the `console.command` service tag instead of overriding this method

Messenger
---------

 * Serializers now return `Envelope<MessageDecodingFailedException>` on decode failure instead of throwing;
   custom serializers that still throw are supported via a BC fallback in receivers
 * Receivers no longer delete messages from the queue on decode failure;
   they are routed through the normal retry/failure transport path instead
 * Add argument `$fetchSize` to `ReceiverInterface::get()` and `QueueReceiverInterface::getFromQueues()`
 * Deprecate `StopWorkerOnTimeLimitListener` in favor of using the `time_limit` worker option

Security
--------

 * Add `getParentRoleNames()` method to `RoleHierarchyInterface`
 * Make `RoleHierarchyInterface::getReachableRoleNames()` return roles as both keys and values
 * Deprecate `SameOriginCsrfTokenManager::onKernelResponse()`, `SameOriginCsrfTokenManager::clearCookies()` and `SameOriginCsrfTokenManager::persistStrategy()`; this logic is now handled automatically by `SameOriginCsrfListener`
 * Deprecate passing the `$eraseCredentials` argument to `AuthenticatorManager::__construct()`, as the `eraseCredentials()` method was removed in Symfony 8.0

SecurityBundle
--------------

 * Deprecate the `security.erase_credentials` config option and the `security.authentication.manager.erase_credentials` container parameter, as the `eraseCredentials()` method was removed in Symfony 8.0

Serializer
----------

 * Deprecate datetime constructor as a fallback, in version 9.0 a `Symfony\Component\Serializer\Exception\NotNormalizableValueException` will be thrown when a date could not be parsed using the default format
 * Change the signature of `PartialDenormalizationException::__construct($data, array $errors)` to `__construct(mixed $data, array $notNormalizableErrors, array $extraAttributesErrors = [])`
 * Deprecate `PartialDenormalizationException::getErrors()`, use `getNotNormalizableValueErrors()` instead

Uid
---

 * Add argument `$format` to `Ulid::isValid()`

Validator
---------

* Deprecate `ConstraintValidatorInterface::initialize()` and `ConstraintValidatorInterface::validate()` in
  favor of `ConstraintValidatorInterface::validateInContext()`. The `ConstraintValidator` abstract class
  handles the context management when extending it. When writing tests with `ConstraintValidatorTestCase`,
  use the new `validate` method to abstract the way to use the constraint validator.

  | Your code                                          | Action required
  |----------------------------------------------------| ---------------
  | extends `ConstraintValidator`                      | Nothing to do
  | implements `ConstraintValidatorInterface` directly | Implement `validateInContext()`
  | tests using `ConstraintValidatorTestCase`          | Call `$this->validate()` instead of `$this->validator->validate()`

VarExporter
-----------

 * Deprecate `Hydrator` and `Instantiator` classes, use `deepclone_hydrate()` from the deepclone extension instead
