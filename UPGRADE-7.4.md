UPGRADE FROM 7.3 to 7.4
=======================

Symfony 7.4 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.4/setup/upgrade_minor.html).

If you're upgrading from a version below 7.3, follow the [7.3 upgrade guide](UPGRADE-7.3.md) first.

BrowserKit
----------

 * Deprecate `AbstractBrowser::useHtml5Parser()`; Symfony 8 will unconditionally use the native HTML5 parser

Cache
-----

 * Bump ext-redis to 6.2 and ext-relay to 0.11 minimum

Config
------

 * Deprecate accessing the internal scope of the loader in PHP config files, use only its public API instead

Console
-------

 * Deprecate `Symfony\Component\Console\Application::add()` in favor of `addCommand()`

DependencyInjection
-------------------

 * Add argument `$target` to `ContainerBuilder::registerAliasForArgument()`
 * Add argument `$throwOnAbstract` to `ContainerBuilder::findTaggedResourceIds()`
 * Deprecate registering a service without a class when its id is a non-existing FQCN
 * Deprecate using `$this` or its internal scope from PHP config files; use the `$loader` variable instead
 * Deprecate XML configuration format, use YAML or PHP instead
 * Deprecate `ExtensionInterface::getXsdValidationBasePath()` and `getNamespace()`;
   bundles that need to support older versions of Symfony can keep the methods
   but need to add the `@deprecated` annotation on them

DoctrineBridge
--------------

 * Deprecate `UniqueEntity::getRequiredOptions()` and `UniqueEntity::getDefaultOption()`
 * Deprecate the `AbstractDoctrineExtension` class; its code is incorporated into the extension classes of Doctrine bundles

DomCrawler
----------

 * Disabling HTML5 parsing is deprecated; Symfony 8 will unconditionally use the native HTML5 parser

Form
----

 * [BC BREAK] The `CurrencyType` returns only the currencies that are active and recognized as [legal tender](https://en.wikipedia.org/wiki/Legal_tender) for the current date; set the `active_at`, and `legal_tender` options to `null` to list all currencies no matter their current state

FrameworkBundle
---------------

 * Deprecate `Symfony\Bundle\FrameworkBundle\Console\Application::add()` in favor of `addCommand()`
 * Deprecate `Symfony\Bundle\FrameworkBundle\Command\WorkflowDumpCommand` in favor of `Symfony\Component\Workflow\Command\WorkflowDumpCommand`

HtmlSanitizer
-------------

 * Use the native HTML5 parser when using PHP 8.4+
 * Deprecate `MastermindsParser`; use `NativeParser` instead
 * [BC BREAK] `ParserInterface::parse()` can now return `\Dom\Node|\DOMNode|null` instead of just `\DOMNode|null`
 * Add argument `$context` to `ParserInterface::parse()`

HttpClient
----------

 * Deprecate using amphp/http-client < 5
 * Deprecate passing an instance of `StoreInterface` as `$cache` argument to `CachingHttpClient` constructor

HttpFoundation
--------------

 * Add argument `$subtypeFallback` to `Request::getFormat()`
 * Deprecate using `Request::sendHeaders()` after headers have already been sent; use a `StreamedResponse` instead
 * Deprecate method `Request::get()`, use properties `->attributes`, `query` or `request` directly instead
 * Deprecate HTTP method override for methods GET, HEAD, CONNECT and TRACE; it will be ignored in Symfony 8.0

HttpKernel
----------

 * Deprecate implementing `__sleep/wakeup()` on kernels; use `__(un)serialize()` instead
 * Deprecate implementing `__sleep/wakeup()` on data collectors; use `__(un)serialize()` instead
 * Make `Profile` final and `Profiler::__sleep()` internal

Mime
----

 * Deprecate implementing `__sleep/wakeup()` on `AbstractPart` implementations; use `__(un)serialize()` instead

MonologBridge
-------------

 * Deprecate class `NotFoundActivationStrategy`, use `HttpCodeActivationStrategy` instead

Routing
-------

 * Deprecate class aliases in the `Annotation` namespace, use attributes instead
 * Deprecate getters and setters in attribute classes in favor of public properties
 * Deprecate accessing the internal scope of the loader in PHP config files, use only its public API instead
 * Deprecate XML configuration format, use YAML, PHP or attributes instead

Security
--------

 * Deprecate extending the `RememberMeDetails` class with a constructor expecting the user FQCN

   Before:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userFqcn, $userIdentifier, $expires, $value);
       }
   }
   ```

   After:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userIdentifier, $expires, $value);
       }
   }
   ```
 * Deprecate callable firewall listeners, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Deprecate `AbstractListener::__invoke`
 * Deprecate `LazyFirewallContext::__invoke()`
 * Deprecate `PersistentTokenInterface::getClass()` and `RememberMeDetails::getUserFqcn()`, the user FQCN will be removed from the remember-me cookie in 8.0
 * Add argument `$accessDecision` to `AccessDecisionStrategyInterface::decide()`;

Serializer
----------

 * Make `AttributeMetadata` and `ClassMetadata` final
 * Deprecate class aliases in the `Annotation` namespace, use attributes instead
 * Deprecate getters in attribute classes in favor of public properties
 * Deprecate `ClassMetadataFactoryCompiler`

String
------

 * Deprecate implementing `__sleep/wakeup()` on string implementations

Translation
-----------

 * Deprecate `TranslatableMessage::__toString`

Uid
---

 * Default to `UuidV7` when using `UuidFactory`

Validator
---------

 * Deprecate handling associative arrays in `GroupSequence`

   *Before*

   ```php
   $groupSequence = GroupSequence(['value' => ['group 1', 'group 2']]);
   ```

   *After*

   ```php
   $groupSequence = GroupSequence(['group 1', 'group 2']);
   ```
 * Deprecate configuring constraint options implicitly with the XML format

   *Before*

   ```xml
   <class name="Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity">
     <constraint name="Callback">
       <value>Symfony\Component\Validator\Tests\Fixtures\CallbackClass</value>
       <value>callback</value>
     </constraint>
   </class>
   ```

   *After*

   ```xml
   <class name="Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity">
     <constraint name="Callback">
       <option name="callback">
         <value>Symfony\Component\Validator\Tests\Fixtures\CallbackClass</value>
         <value>callback</value>
       </option>
     </constraint>
   </class>
   ```
 * Deprecate configuring constraint options implicitly with the YAML format

   *Before*

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback: validateMeStatic
       - Callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```

   *After*

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback:
           callback: validateMeStatic
       - Callback:
           callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```
 * Deprecate implementing `__sleep/wakeup()` on `GenericMetadata` implementations; use `__(un)serialize()` instead
 * Deprecate passing a list of choices to the first argument of the `Choice` constraint. Use the `choices` option instead
 * Deprecate `getRequiredOptions()` and `getDefaultOption()` methods of the `All`, `AtLeastOneOf`, `CardScheme`, `Collection`,
   `CssColor`, `Expression`, `Regex`, `Sequentially`, `Type`, and `When` constraints
 * Deprecate evaluating options in the base `Constraint` class. Initialize properties in the constructor of the concrete constraint
   class instead

   *Before*

   ```php
   class CustomConstraint extends Constraint
   {
       public $option1;
       public $option2;

       public function __construct(?array $options = null)
       {
           parent::__construct($options);
       }
   }
   ```

   *After*

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;

   class CustomConstraint extends Constraint
   {
       #[HasNamedArguments]
       public function __construct(
           public $option1 = null,
           public $option2 = null,
           ?array $groups = null,
           mixed $payload = null,
       ) {
           parent::__construct(null, $groups, $payload);
       }
   }
   ```

 * Deprecate the `getRequiredOptions()` method of the base `Constraint` class. Use mandatory constructor arguments instead

   *Before*

   ```php
   class CustomConstraint extends Constraint
   {
       public $option1;
       public $option2;

       public function __construct(?array $options = null)
       {
           parent::__construct($options);
       }

       public function getRequiredOptions()
       {
           return ['option1'];
       }
   }
   ```

   *After*

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;

   class CustomConstraint extends Constraint
   {
       #[HasNamedArguments]
       public function __construct(
           public $option1,
           public $option2 = null,
           ?array $groups = null,
           mixed $payload = null,
       ) {
           parent::__construct(null, $groups, $payload);
       }
   }
   ```
 * Deprecate the `normalizeOptions()` and `getDefaultOption()` methods of the base `Constraint` class without replacements;
   overriding them in child constraint will not have any effects starting with Symfony 8.0
 * Deprecate passing an array of options to the `Composite` constraint class. Initialize the properties referenced with `getNestedConstraints()`
   in child classes before calling the constructor of `Composite`

   *Before*

   ```php
   class CustomCompositeConstraint extends Composite
   {
       public array $constraints = [];

       public function __construct(?array $options = null)
       {
           parent::__construct($options);
       }

       protected function getCompositeOption(): string
       {
           return 'constraints';
       }
   }
   ```

   *After*

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;

   class CustomCompositeConstraint extends Composite
   {
       #[HasNamedArguments]
       public function __construct(
           public array $constraints,
           ?array $groups = null,
           mixed $payload = null)
       {
           parent::__construct(null, $groups, $payload);
       }
   }
   ```
