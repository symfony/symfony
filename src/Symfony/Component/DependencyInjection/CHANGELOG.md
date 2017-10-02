CHANGELOG
=========

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
