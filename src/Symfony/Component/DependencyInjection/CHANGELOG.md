CHANGELOG
=========

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
 * added `%env(url:...)%` processor to convert an URL or DNS into an array of components
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
