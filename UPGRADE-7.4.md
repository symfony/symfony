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

 * Deprecate `Symfony\Component\Console\Application::add()` in favor of `Symfony\Component\Console\Application::addCommand()`

DependencyInjection
-------------------

 * Add argument `$target` to `ContainerBuilder::registerAliasForArgument()`

DoctrineBridge
--------------

 * Deprecate `UniqueEntity::getRequiredOptions()` and `UniqueEntity::getDefaultOption()`

EventDispatcher
---------------
 * Deprecate attribute `event` of the `kernel.event_listener` tag  in favor of `events` attribute

FrameworkBundle
---------------

 * Deprecate `Symfony\Bundle\FrameworkBundle\Console\Application::add()` in favor of `Symfony\Bundle\FrameworkBundle\Console\Application::addCommand()`

HttpClient
----------

 * Deprecate using amphp/http-client < 5

HttpFoundation
--------------

 * Deprecate using `Request::sendHeaders()` after headers have already been sent; use a `StreamedResponse` instead

Security
--------

 * Deprecate callable firewall listeners, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Deprecate `AbstractListener::__invoke`
 * Deprecate `LazyFirewallContext::__invoke()`

Translation
-----------

 * Deprecate `TranslatableMessage::__toString`

Validator
---------

 * Deprecate `getRequiredOptions()` and `getDefaultOption()` methods of the `All`, `AtLeastOneOf`, `CardScheme`, `Collection`,
   `CssColor`, `Expression`, `Regex`, `Sequentially`, `Type`, and `When` constraints
 * Deprecate evaluating options in the base `Constraint` class. Initialize properties in the constructor of the concrete constraint
   class instead.

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
       public $option1;
       public $option2;

       #[HasNamedArguments]
       public function __construct($option1 = null, $option2 = null, ?array $groups = null, mixed $payload = null)
       {
           parent::__construct(null, $groups, $payload);

           $this->option1 = $option1;
           $this->option2 = $option2;
       }
   }
   ```

 * Deprecate the `getRequiredOptions()` method of the base `Constraint` class. Use mandatory constructor arguments instead.

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
       public $option1;
       public $option2;

       #[HasNamedArguments]
       public function __construct($option1, $option2 = null, ?array $groups = null, mixed $payload = null)
       {
           parent::__construct(null, $groups, $payload);

           $this->option1 = $option1;
           $this->option2 = $option2;
       }
   }
   ```
 * Deprecate the `normalizeOptions()` and `getDefaultOption()` methods of the base `Constraint` class without replacements.
   Overriding them in child constraint will not have any effects starting with Symfony 8.0.
 * Deprecate passing an array of options to the `Composite` constraint class. Initialize the properties referenced with `getNestedConstraints()`
   in child classes before calling the constructor of `Composite`.

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
       public array $constraints = [];

       #[HasNamedArguments]
       public function __construct(array $constraints, ?array $groups = null, mixed $payload = null)
       {
           $this->constraints = $constraints;

           parent::__construct(null, $groups, $payload);
       }
   }
   ```
