UPGRADE FROM 7.3 to 7.4
=======================

Symfony 7.4 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.4/setup/upgrade_minor.html).

If you're upgrading from a version below 7.3, follow the [7.3 upgrade guide](UPGRADE-7.3.md) first.

Cache
-----

 * Bump ext-redis to 6.2 and ext-relay to 0.11 minimum

Console
-------

 * Deprecate `Symfony\Component\Console\Application::add()` in favor of `addCommand()`

BrowserKit
----------

 * Leverage the native HTML5 parser when using PHP 8.4+

DependencyInjection
-------------------

 * Add argument `$target` to `ContainerBuilder::registerAliasForArgument()`
 * Deprecate registering a service without a class when its id is a non-existing FQCN

DoctrineBridge
--------------

 * Deprecate `UniqueEntity::getRequiredOptions()` and `UniqueEntity::getDefaultOption()`

DomCrawler
----------

 * [BC BREAK] Type widening on public and protected methods/properties to support both
   `Dom\*` and `DOM*` native classes for:
   * properties `FormField::$document`, `$xpath`, `$node` and method `getLabel()`
   * methods `Form::getFormNode()` and `addField()`
   * property `AbstractUriElement::$node`, and methods `getNode()` and `setNode()`
   * methods `Crawler::add()`, `addDocument()`, `addNodeList()`, `addNode()`, `getNode()` and `sibling()`

FrameworkBundle
---------------

 * Deprecate `Symfony\Bundle\FrameworkBundle\Console\Application::add()` in favor of `addCommand()`

HtmlSanitizer
-------------

 * Use the native HTML5 parser when using PHP 8.4+
 * Deprecate `MastermindsParser`; use `NativeParser` instead
 * [BC BREAK] `ParserInterface::parse()` can now return `\Dom\Node|\DOMNode|null` instead of just `\DOMNode|null`
 * Add argument `$context` to `ParserInterface::parse()`

HttpClient
----------

 * Deprecate using amphp/http-client < 5

HttpFoundation
--------------

 * Deprecate using `Request::sendHeaders()` after headers have already been sent; use a `StreamedResponse` instead

HttpKernel
----------

 * Deprecate implementing `__sleep/wakeup()` on kernels; use `__(un)serialize()` instead
 * Deprecate implementing `__sleep/wakeup()` on data collectors; use `__(un)serialize()` instead
 * Make `Profile` final and `Profiler::__sleep()` internal

Mime
----

 * Deprecate implementing `__sleep/wakeup()` on `AbstractPart` implementations; use `__(un)serialize()` instead

Routing
-------

 * Deprecate `getEnv()` and `setEnv()` methods of the `Symfony\Component\Routing\Attribute\Route` class in favor of the plurialized `getEnvs()` and `setEnvs()` methods

Security
--------

 * Deprecate callable firewall listeners, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Deprecate `AbstractListener::__invoke`
 * Deprecate `LazyFirewallContext::__invoke()`

Serializer
----------

 * Make `AttributeMetadata` and `ClassMetadata` final

String
------

 * Deprecate implementing `__sleep/wakeup()` on string implementations

Translation
-----------

 * Deprecate `TranslatableMessage::__toString`

Validator
---------

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
