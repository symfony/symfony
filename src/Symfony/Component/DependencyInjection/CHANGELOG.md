CHANGELOG
=========

6.2
---

 * Use lazy-loading ghost objects and virtual proxies out of the box
 * Add arguments `&$asGhostObject` and `$id` to LazyProxy's `DumperInterface` to allow using ghost objects for lazy loading services
 * Add `enum` env var processor
 * Add `shuffle` env var processor
 * Allow #[When] to be extended
 * Change the signature of `ContainerAwareInterface::setContainer()` to `setContainer(?ContainerInterface)`
 * Deprecate calling `ContainerAwareTrait::setContainer()` without arguments
 * Deprecate using numeric parameter names
 * Add support for tagged iterators/locators `exclude` option to the xml and yaml loaders/dumpers
 * Allow injecting `string $env` into php config closures

6.1
---

 * Add `#[MapDecorated]` attribute telling to which parameter the decorated service should be mapped in a decorator
 * Add `#[AsDecorator]` attribute to make a service decorates another
 * Add `$exclude` to `TaggedIterator` and `TaggedLocator` attributes
 * Add `$exclude` to `tagged_iterator` and `tagged_locator` configurator
 * Add an `env` function to the expression language provider
 * Add an `Autowire` attribute to tell a parameter how to be autowired
 * Allow using expressions as service factories
 * Add argument type `closure` to help passing closures to services
 * Deprecate `ReferenceSetArgumentTrait`
 * Add `AbstractExtension` class for DI configuration/definition on a single file

6.0
---

 * Remove `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead
 * Remove `inline()` in favor of `inline_service()` and `ref()` in favor of `service()` when using the PHP-DSL
 * Remove `Definition::getDeprecationMessage()`, use `Definition::getDeprecation()` instead
 * Remove `Alias::getDeprecationMessage()`, use `Alias::getDeprecation()` instead
 * Remove the `Psr\Container\ContainerInterface` and `Symfony\Component\DependencyInjection\ContainerInterface` aliases of the `service_container` service

5.4
---
 * Add `$defaultIndexMethod` and `$defaultPriorityMethod` to `TaggedIterator` and `TaggedLocator` attributes
 * Add `service_closure()` to the PHP-DSL
 * Add support for autoconfigurable attributes on methods, properties and parameters
 * Make auto-aliases private by default
 * Add support for autowiring union and intersection types

5.3
---

 * Add `ServicesConfigurator::remove()` in the PHP-DSL
 * Add `%env(not:...)%` processor to negate boolean values
 * Add support for loading autoconfiguration rules via the `#[Autoconfigure]` and `#[AutoconfigureTag]` attributes on PHP 8
 * Add `#[AsTaggedItem]` attribute for defining the index and priority of classes found in tagged iterators/locators
 * Add autoconfigurable attributes
 * Add support for autowiring tagged iterators and locators via attributes on PHP 8
 * Add support for per-env configuration in XML and Yaml loaders
 * Add `ContainerBuilder::willBeAvailable()` to help with conditional configuration
 * Add support an integer return value for default_index_method
 * Add `#[When(env: 'foo')]` to skip autoregistering a class when the env doesn't match
 * Add `env()` and `EnvConfigurator` in the PHP-DSL
 * Add support for `ConfigBuilder` in the `PhpFileLoader`
 * Add `ContainerConfigurator::env()` to get the current environment
 * Add `#[Target]` to tell how a dependency is used and hint named autowiring aliases

5.2.0
-----

 * added `param()` and `abstract_arg()` in the PHP-DSL
 * deprecated `Definition::setPrivate()` and `Alias::setPrivate()`, use `setPublic()` instead
 * added support for the `#[Required]` attribute

5.1.0
-----

 * deprecated `inline()` in favor of `inline_service()` and `ref()` in favor of `service()` when using the PHP-DSL
 * allow decorators to reference their decorated service using the special `.inner` id
 * added support to autowire public typed properties in php 7.4
 * added support for defining method calls, a configurator, and property setters in `InlineServiceConfigurator`
 * added possibility to define abstract service arguments
 * allowed mixing "parent" and instanceof-conditionals/defaults/bindings
 * updated the signature of method `Definition::setDeprecated()` to `Definition::setDeprecation(string $package, string $version, string $message)`
 * updated the signature of method `Alias::setDeprecated()` to `Alias::setDeprecation(string $package, string $version, string $message)`
 * updated the signature of method `DeprecateTrait::deprecate()` to `DeprecateTrait::deprecation(string $package, string $version, string $message)`
 * deprecated the `Psr\Container\ContainerInterface` and `Symfony\Component\DependencyInjection\ContainerInterface` aliases of the `service_container` service,
   configure them explicitly instead
 * added class `Symfony\Component\DependencyInjection\Dumper\Preloader` to help with preloading on PHP 7.4+
 * added tags `container.preload`/`.no_preload` to declare extra classes to preload/services to not preload
 * allowed loading and dumping tags with an attribute named "name"
 * deprecated `Definition::getDeprecationMessage()`, use `Definition::getDeprecation()` instead
 * deprecated `Alias::getDeprecationMessage()`, use `Alias::getDeprecation()` instead
 * added support of PHP8 static return type for withers
 * added `AliasDeprecatedPublicServicesPass` to deprecate public services to private

5.0.0
-----

 * removed support for auto-discovered extension configuration class which does not implement `ConfigurationInterface`
 * removed support for non-string default env() parameters
 * moved `ServiceSubscriberInterface` to the `Symfony\Contracts\Service` namespace
 * removed `RepeatedPass` and `RepeatablePassInterface`
 * removed support for short factory/configurator syntax from `YamlFileLoader`
 * removed `ResettableContainerInterface`, use `ResetInterface` instead
 * added argument `$returnsClone` to `Definition::addMethodCall()`
 * removed `tagged`, use `tagged_iterator` instead

4.4.0
-----

 * added `CheckTypeDeclarationsPass` to check injected parameters type during compilation
 * added support for opcache.preload by generating a preloading script in the cache folder
 * added support for dumping the container in one file instead of many files
 * deprecated support for short factories and short configurators in Yaml
 * added `tagged_iterator` alias for `tagged` which might be deprecated in a future version
 * deprecated passing an instance of `Symfony\Component\DependencyInjection\Parameter` as class name to `Symfony\Component\DependencyInjection\Definition`
 * added support for binding iterable and tagged services
 * made singly-implemented interfaces detection be scoped by file
 * added ability to define a static priority method for tagged service
 * added support for improved syntax to define method calls in Yaml
 * made the `%env(base64:...)%` processor able to decode base64url
 * added ability to choose behavior of decorations on non existent decorated services

4.3.0
-----

 * added `%env(trim:...)%` processor to trim a string value
 * added `%env(default:param_name:...)%` processor to fallback to a parameter or to null when using `%env(default::...)%`
 * added `%env(url:...)%` processor to convert a URL or DNS into an array of components
 * added `%env(query_string:...)%` processor to convert a query string into an array of key values
 * added support for deprecating aliases
 * made `ContainerParametersResource` final and not implement `Serializable` anymore
 * added `ReverseContainer`: a container that turns services back to their ids
 * added ability to define an index for a tagged collection
 * added ability to define an index for services in an injected service locator argument
 * made `ServiceLocator` implement `ServiceProviderInterface`
 * deprecated support for non-string default env() parameters
 * added `%env(require:...)%` processor to `require()` a PHP file and use the value returned from it

4.2.0
-----

 * added `ContainerBuilder::registerAliasForArgument()` to support autowiring by type+name
 * added support for binding by type+name
 * added `ServiceSubscriberTrait` to ease implementing `ServiceSubscriberInterface` using methods' return types
 * added `ServiceLocatorArgument` and `!service_locator` config tag for creating optimized service-locators
 * added support for autoconfiguring bindings
 * added `%env(key:...)%` processor to fetch a specific key from an array
 * deprecated `ServiceSubscriberInterface`, use the same interface from the `Symfony\Contracts\Service` namespace instead
 * deprecated `ResettableContainerInterface`, use `Symfony\Contracts\Service\ResetInterface` instead

4.1.0
-----

 * added support for variadics in named arguments
 * added PSR-11 `ContainerBagInterface` and its `ContainerBag` implementation to access parameters as-a-service
 * added support for service's decorators autowiring
 * deprecated the `TypedReference::canBeAutoregistered()` and  `TypedReference::getRequiringClass()` methods
 * environment variables are validated when used in extension configuration
 * deprecated support for auto-discovered extension configuration class which does not implement `ConfigurationInterface`

4.0.0
-----

 * Relying on service auto-registration while autowiring is not supported anymore.
   Explicitly inject your dependencies or create services whose ids are
   their fully-qualified class name.

   Before:

   ```php
   namespace App\Controller;

   use App\Mailer;

   class DefaultController
   {
       public function __construct(Mailer $mailer) {
           // ...
       }

       // ...
   }
   ```
   ```yml
   services:
       App\Controller\DefaultController:
           autowire: true
   ```

   After:

   ```php
   // same PHP code
   ```
   ```yml
   services:
       App\Controller\DefaultController:
           autowire: true

       # or
       # App\Controller\DefaultController:
       #     arguments: { $mailer: "@App\Mailer" }

       App\Mailer:
           autowire: true
    ```
 * removed autowiring services based on the types they implement
 * added a third `$methodName` argument to the `getProxyFactoryCode()` method
   of the `DumperInterface`
 * removed support for autowiring types
 * removed `Container::isFrozen`
 * removed support for dumping an ucompiled container in `PhpDumper`
 * removed support for generating a dumped `Container` without populating the method map
 * removed support for case insensitive service identifiers
 * removed the `DefinitionDecorator` class, replaced by `ChildDefinition`
 * removed the `AutowireServiceResource` class and related `AutowirePass::createResourceForClass()` method
 * removed `LoggingFormatter`, `Compiler::getLoggingFormatter()` and `addLogMessage()` class and methods, use the `ContainerBuilder::log()` method instead
 * removed `FactoryReturnTypePass`
 * removed `ContainerBuilder::addClassResource()`, use the `addObjectResource()` or the `getReflectionClass()` method instead.
 * removed support for top-level anonymous services
 * removed silent behavior for unused attributes and elements
 * removed support for setting and accessing private services in `Container`
 * removed support for setting pre-defined services in `Container`
 * removed support for case insensitivity of parameter names
 * removed `AutowireExceptionPass` and `AutowirePass::getAutowiringExceptions()`, use `Definition::addError()` and the `DefinitionErrorExceptionPass` instead

3.4.0
-----

 * moved the `ExtensionCompilerPass` to before-optimization passes with priority -1000
 * deprecated "public-by-default" definitions and aliases, the new default will be "private" in 4.0
 * added `EnvVarProcessorInterface` and corresponding "container.env_var_processor" tag for processing env vars
 * added support for ignore-on-uninitialized references
 * deprecated service auto-registration while autowiring
 * deprecated the ability to check for the initialization of a private service with the `Container::initialized()` method
 * deprecated support for top-level anonymous services in XML
 * deprecated case insensitivity of parameter names
 * deprecated the `ResolveDefinitionTemplatesPass` class in favor of `ResolveChildDefinitionsPass`
 * added `TaggedIteratorArgument` with YAML (`!tagged foo`) and XML (`<service type="tagged"/>`) support
 * deprecated `AutowireExceptionPass` and `AutowirePass::getAutowiringExceptions()`, use `Definition::addError()` and the `DefinitionErrorExceptionPass` instead

3.3.0
-----

 * deprecated autowiring services based on the types they implement;
   rename (or alias) your services to their FQCN id to make them autowirable
 * added "ServiceSubscriberInterface" - to allow for per-class explicit service-locator definitions
 * added "container.service_locator" tag for defining service-locator services
 * added anonymous services support in YAML configuration files using the `!service` tag.
 * added "TypedReference" and "ServiceClosureArgument" for creating service-locator services
 * added `ServiceLocator` - a PSR-11 container holding a set of services to be lazily loaded
 * added "instanceof" section for local interface-defined configs
 * added prototype services for PSR4-based discovery and registration
 * added `ContainerBuilder::getReflectionClass()` for retrieving and tracking reflection class info
 * deprecated `ContainerBuilder::getClassResource()`, use `ContainerBuilder::getReflectionClass()` or `ContainerBuilder::addObjectResource()` instead
 * added `ContainerBuilder::fileExists()` for checking and tracking file or directory existence
 * deprecated autowiring-types, use aliases instead
 * added support for omitting the factory class name in a service definition if the definition class is set
 * deprecated case insensitivity of service identifiers
 * added "iterator" argument type for lazy iteration over a set of values and services
 * added file-wide configurable defaults for service attributes "public", "tags",
   "autowire" and "autoconfigure"
 * made the "class" attribute optional, using the "id" as fallback
 * using the `PhpDumper` with an uncompiled `ContainerBuilder` is deprecated and
   will not be supported anymore in 4.0
 * deprecated the `DefinitionDecorator` class in favor of `ChildDefinition`
 * allow config files to be loaded using a glob pattern
 * [BC BREAK] the `NullDumper` class is now final

3.2.0
-----

 * allowed to prioritize compiler passes by introducing a third argument to `PassConfig::addPass()`, to `Compiler::addPass` and to `ContainerBuilder::addCompilerPass()`
 * added support for PHP constants in YAML configuration files
 * deprecated the ability to set or unset a private service with the `Container::set()` method
 * deprecated the ability to check for the existence of a private service with the `Container::has()` method
 * deprecated the ability to request a private service with the `Container::get()` method
 * deprecated support for generating a dumped `Container` without populating the method map

3.0.0
-----

 * removed all deprecated codes from 2.x versions

2.8.0
-----

 * deprecated the abstract ContainerAware class in favor of ContainerAwareTrait
 * deprecated IntrospectableContainerInterface, to be merged with ContainerInterface in 3.0
 * allowed specifying a directory to recursively load all configuration files it contains
 * deprecated the concept of scopes
 * added `Definition::setShared()` and `Definition::isShared()`
 * added ResettableContainerInterface to be able to reset the container to release memory on shutdown
 * added a way to define the priority of service decoration
 * added support for service autowiring

2.7.0
-----

 * deprecated synchronized services

2.6.0
-----

 * added new factory syntax and deprecated the old one

2.5.0
-----

 * added DecoratorServicePass and a way to override a service definition (Definition::setDecoratedService())
 * deprecated SimpleXMLElement class.

2.4.0
-----

 * added support for expressions in service definitions
 * added ContainerAwareTrait to add default container aware behavior to a class

2.2.0
-----

 * added Extension::isConfigEnabled() to ease working with enableable configurations
 * added an Extension base class with sensible defaults to be used in conjunction
   with the Config component.
 * added PrependExtensionInterface (to be able to allow extensions to prepend
   application configuration settings for any Bundle)

2.1.0
-----

 * added IntrospectableContainerInterface (to be able to check if a service
   has been initialized or not)
 * added ConfigurationExtensionInterface
 * added Definition::clearTag()
 * component exceptions that inherit base SPL classes are now used exclusively
   (this includes dumped containers)
 * [BC BREAK] fixed unescaping of class arguments, method
   ParameterBag::unescapeValue() was made public
