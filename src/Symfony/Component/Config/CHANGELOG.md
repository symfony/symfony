CHANGELOG
=========

8.0
---

 * Remove support for accessing the internal scope of the loader in PHP config files, use only its public API instead
 * Add argument `$singular` to `NodeBuilder::arrayNode()`
 * Add argument `$info` to `ArrayNodeDefinition::canBeDisabled()` and `canBeEnabled()`
 * Ensure configuration nodes do not have both `isRequired()` and `defaultValue()`
 * Remove generation of fluent methods in config builders

7.4
---

 * Add TagAwareAdapterInterface to NullAdapter
 * Add argument `$singular` to `NodeBuilder::arrayNode()` to decouple plurals/singulars from XML
 * Add support for `defaultNull()` on `ArrayNodeDefinition`
 * Add `ArrayNodeDefinition::acceptAndWrap()` to list alternative types that should be accepted and wrapped in an array
 * Add array-shapes to generated config builders
 * Deprecate accessing the internal scope of the loader in PHP config files, use only its public API instead
 * Deprecate setting a default value to a node that is required, and vice versa
 * Deprecate fluent config builders, return PHP arrays from your config instead

7.3
---

 * Add `ExprBuilder::ifFalse()`
 * Add support for info on `ArrayNodeDefinition::canBeEnabled()` and `ArrayNodeDefinition::canBeDisabled()`
 * Allow using an enum FQCN with `EnumNode`
 * Add `NodeDefinition::docUrl()`

7.2
---

 * Add `#[WhenNot]` attribute to prevent service from being registered in a specific environment
 * Generate a meta file in JSON format for resource tracking
 * Add `SkippingResourceChecker`
 * Add support for `defaultNull()` on `BooleanNodeDefinition`
 * Add `StringNode` and `StringNodeDefinition`
 * Add `ArrayNodeDefinition::stringPrototype()` method
 * Add `NodeBuilder::stringNode()` method

7.1
---

 * Allow custom meta location in `ResourceCheckerConfigCache`
 * Allow custom meta location in `ConfigCache`

7.0
---

 * Require explicit argument when calling `NodeBuilder::setParent()`

6.3
---

 * Allow enum values in `EnumNode`

6.2
---

 * Deprecate calling `NodeBuilder::setParent()` without any arguments
 * Add a more accurate typehint in generated PHP config

6.1
---

 * Allow using environment variables in `EnumNode`
 * Add Node's information in generated Config
 * Add `DefinitionFileLoader` class to load a TreeBuilder definition from an external file
 * Add `DefinitionConfigurator` helper

6.0
---

 * Remove `BaseNode::getDeprecationMessage()`

5.3.0
-----

 * Add support for generating `ConfigBuilder` for extensions

5.1.0
-----

 * updated the signature of method `NodeDefinition::setDeprecated()` to `NodeDefinition::setDeprecation(string $package, string $version, string $message)`
 * updated the signature of method `BaseNode::setDeprecated()` to `BaseNode::setDeprecation(string $package, string $version, string $message)`
 * deprecated passing a null message to `BaseNode::setDeprecated()` to un-deprecate a node
 * deprecated `BaseNode::getDeprecationMessage()`, use `BaseNode::getDeprecation()` instead

5.0.0
-----

 * Dropped support for constructing a `TreeBuilder` without passing root node information.
 * Removed the `root()` method in `TreeBuilder`, pass the root node information to the constructor instead
 * Added method `getChildNodeDefinitions()` to ParentNodeDefinitionInterface
 * Removed `FileLoaderLoadException`, use `LoaderLoadException` instead

4.4.0
-----

 * added a way to exclude patterns of resources from being imported by the `import()` method

4.3.0
-----

 * deprecated using environment variables with `cannotBeEmpty()` if the value is validated with `validate()`
 * made `Resource\*` classes final and not implement `Serializable` anymore
 * deprecated the `root()` method in `TreeBuilder`, pass the root node information to the constructor instead

4.2.0
-----

 * deprecated constructing a `TreeBuilder` without passing root node information
 * renamed `FileLoaderLoadException` to `LoaderLoadException`

4.1.0
-----

 * added `setPathSeparator` method to `NodeBuilder` class
 * added third `$pathSeparator` constructor argument to `BaseNode`
 * the `Processor` class has been made final

4.0.0
-----

 * removed `ConfigCachePass`

3.4.0
-----

 * added `setDeprecated()` method to indicate a deprecated node
 * added `XmlUtils::parse()` method to parse an XML string
 * deprecated `ConfigCachePass`

3.3.0
-----

 * added `ReflectionClassResource` class
 * added second `$exists` constructor argument to `ClassExistenceResource`
 * made `ClassExistenceResource` work with interfaces and traits
 * added `ConfigCachePass` (originally in FrameworkBundle)
 * added `castToArray()` helper to turn any config value into an array

3.0.0
-----

 * removed `ReferenceDumper` class
 * removed the `ResourceInterface::isFresh()` method
 * removed `BCResourceInterfaceChecker` class
 * removed `ResourceInterface::getResource()` method

2.8.0
-----

The edge case of defining just one value for nodes of type Enum is now allowed:

```php
$rootNode
    ->children()
        ->enumNode('variable')
            ->values(['value'])
        ->end()
    ->end()
;
```

Before: `InvalidArgumentException` (variable must contain at least two
distinct elements).
After: the code will work as expected and it will restrict the values of the
`variable` option to just `value`.

 * deprecated the `ResourceInterface::isFresh()` method. If you implement custom resource types and they
   can be validated that way, make them implement the new `SelfCheckingResourceInterface`.
 * deprecated the getResource() method in ResourceInterface. You can still call this method
   on concrete classes implementing the interface, but it does not make sense at the interface
   level as you need to know about the particular type of resource at hand to understand the
   semantics of the returned value.

2.7.0
-----

 * added `ConfigCacheInterface`, `ConfigCacheFactoryInterface` and a basic `ConfigCacheFactory`
   implementation to delegate creation of ConfigCache instances

2.2.0
-----

 * added `ArrayNodeDefinition::canBeEnabled()` and `ArrayNodeDefinition::canBeDisabled()`
   to ease configuration when some sections are respectively disabled / enabled
   by default.
 * added a `normalizeKeys()` method for array nodes (to avoid key normalization)
 * added numerical type handling for config definitions
 * added convenience methods for optional configuration sections to `ArrayNodeDefinition`
 * added a utils class for XML manipulations

2.1.0
-----

 * added a way to add documentation on configuration
 * implemented `Serializable` on resources
 * `LoaderResolverInterface` is now used instead of `LoaderResolver` for type
   hinting
