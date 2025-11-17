CHANGELOG
=========

8.0
---

 * Remove support for configuring constraint options implicitly with the XML format

   Before:

   ```xml
   <class name="Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity">
     <constraint name="Callback">
       <value>Symfony\Component\Validator\Tests\Fixtures\CallbackClass</value>
       <value>callback</value>
     </constraint>
   </class>
   ```

   After:

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
 * Remove support for configuring constraint options implicitly with the YAML format

   Before:

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback: validateMeStatic
       - Callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```

   After:

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback:
           callback: validateMeStatic
       - Callback:
           callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```
 * Remove support for passing associative arrays to `GroupSequence`

   Before:

   ```php
   $groupSequence = GroupSequence(['value' => ['group 1', 'group 2']]);
   ```

   After:

   ```php
   $groupSequence = GroupSequence(['group 1', 'group 2']);
   ```
 * Change the default value of the `$requireTld` option of the `Url` constraint to `true`
 * Add method `getGroupProvider()` to `ClassMetadataInterface`
 * Replace `__sleep/wakeup()` by `__(un)serialize()`  on `GenericMetadata` implementations
 * Remove the `getRequiredOptions()` and `getDefaultOption()` methods from the `All`, `AtLeastOneOf`, `CardScheme`, `Collection`,
   `CssColor`, `Expression`, `Regex`, `Sequentially`, `Type`, and `When` constraints
 * Remove support for evaluating options in the base `Constraint` class. Initialize properties in the constructor of the concrete constraint
   class instead.

   Before:

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

   After:

   ```php
   class CustomConstraint extends Constraint
   {
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

 * Remove the `getRequiredOptions()` method from the base `Constraint` class. Use mandatory constructor arguments instead.

   Before:

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

   After:

   ```php
   class CustomConstraint extends Constraint
   {
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
 * Remove the `normalizeOptions()` and `getDefaultOption()` methods of the base `Constraint` class without replacements.
   Overriding them in child constraint does not have any effects.
 * Remove support for passing an array of options to the `Composite` constraint class. Initialize the properties referenced with `getNestedConstraints()`
   in child classes before calling the constructor of `Composite`.

   Before:

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

   After:

   ```php
   class CustomCompositeConstraint extends Composite
   {
       public function __construct(
           public array $constraints,
           ?array $groups = null,
           mixed $payload = null,
       ) {
           parent::__construct(null, $groups, $payload);
       }
   }
   ```
 * Remove `Bic::INVALID_BANK_CODE_ERROR` constant. This error code was not used in the Bic constraint validator anymore

7.4
---

 * Deprecate handling associative arrays in `GroupSequence`

   Before:

   ```php
   $groupSequence = GroupSequence(['value' => ['group 1', 'group 2']]);
   ```

   After:

   ```php
   $groupSequence = GroupSequence(['group 1', 'group 2']);
   ```
 * Deprecate configuring constraint options implicitly with the XML format

   Before:

   ```xml
   <class name="Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity">
    <constraint name="Callback">
      <value>Symfony\Component\Validator\Tests\Fixtures\CallbackClass</value>
      <value>callback</value>
    </constraint>
   </class>
   ```

   After:

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

   Before:

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback: validateMeStatic
       - Callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```

   After:

   ```yaml
   Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity:
     constraints:
       - Callback:
           callback: validateMeStatic
       - Callback:
           callback: [Symfony\Component\Validator\Tests\Fixtures\CallbackClass, callback]
   ```
 * Add `#[ExtendsValidationFor]` to declare new constraints for a class
 * Add `ValidatorBuilder::addAttributeMappings()` and `AttributeMetadataPass` to declare compile-time constraint metadata using attributes
 * Add the `Video` constraint for validating video files
 * Deprecate implementing `__sleep/wakeup()` on `GenericMetadata` implementations; use `__(un)serialize()` instead
 * Deprecate passing a list of choices to the first argument of the `Choice` constraint. Use the `choices` option instead
 * Add the `min` and `max` parameter to the `Length` constraint violation
 * Deprecate `getRequiredOptions()` and `getDefaultOption()` methods of the `All`, `AtLeastOneOf`, `CardScheme`, `Collection`,
   `CssColor`, `Expression`, `Regex`, `Sequentially`, `Type`, and `When` constraints
 * Deprecate evaluating options in the base `Constraint` class. Initialize properties in the constructor of the concrete constraint
   class instead.

   Before:

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

   After:

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
 * Deprecate the `getRequiredOptions()` method of the base `Constraint` class. Use mandatory constructor arguments instead.

   Before:

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

   After:

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
 * Deprecate the `normalizeOptions()` and `getDefaultOption()` methods of the base `Constraint` class without replacements.
   Overriding them in child constraint will not have any effects starting with Symfony 8.0.
 * Deprecate passing an array of options to the `Composite` constraint class. Initialize the properties referenced with `getNestedConstraints()`
   in child classes before calling the constructor of `Composite`.

   Before:

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

   After:

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

7.3
---

 * Add the `filenameCharset` and `filenameCountUnit` options to the `File` constraint
 * Deprecate defining custom constraints not supporting named arguments

   Before:

   ```php
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       public function __construct(array $options)
       {
           // ...
       }
   }
   ```

   After:

   ```php
   use Symfony\Component\Validator\Attribute\HasNamedArguments;
   use Symfony\Component\Validator\Constraint;

   class CustomConstraint extends Constraint
   {
       #[HasNamedArguments]
       public function __construct($option1, $option2, $groups, $payload)
       {
           // ...
       }
   }
   ```
 * Deprecate passing an array of options to the constructors of the constraint classes, pass each option as a dedicated argument instead

   Before:

   ```php
   new NotNull([
       'groups' => ['foo', 'bar'],
       'message' => 'a custom constraint violation message',
   ])
   ```

   After:

   ```php
   new NotNull(
       groups: ['foo', 'bar'],
       message: 'a custom constraint violation message',
   )
   ```
 * Add support for ratio checks for SVG files to the `Image` constraint
 * Add support for the `otherwise` option in the `When` constraint
 * Add support for multiple fields containing nested constraints in `Composite` constraints
 * Add the `stopOnFirstError` option to the `Unique` constraint to validate all elements
 * Add support for closures in the `When` constraint

7.2
---

 * `IbanValidator` accepts IBANs containing non-breaking and narrow non-breaking spaces
 * Make `PasswordStrengthValidator::estimateStrength()` public
 * Add the `Yaml` constraint for validating YAML content
 * Add `errorPath` to Unique constraint
 * Add the `format` option to the `Ulid` constraint to allow accepting different ULID formats
 * Add the `WordCount` constraint
 * Add the `Week` constraint
 * Add `CompoundConstraintTestCase` to ease testing Compound Constraints
 * Add context variable to `WhenValidator`
 * Add `format` parameter to `DateTime` constraint violation message

7.1
---

 * Deprecate not passing a value for the `requireTld` option to the `Url` constraint (the default value will become `true` in 8.0)
 * Add the calculated strength to violations in `PasswordStrengthValidator`
 * Add support for `Stringable` values when using the `Cidr`, `CssColor`, `ExpressionSyntax` and `PasswordStrength` constraints
 * Add `MacAddress` constraint
 * Add `*_NO_PUBLIC`, `*_ONLY_PRIVATE` and `*_ONLY_RESERVED` versions to `Ip` constraint
 * Possibility to use all `Ip` constraint versions for `Cidr` constraint
 * Add `list` and `associative_array` types to `Type` constraint
 * Add the `Charset` constraint
 * Add the `requireTld` option to the `Url` constraint
 * Deprecate `Bic::INVALID_BANK_CODE_ERROR`

7.0
---

 * Add methods `getConstraint()`, `getCause()` and `__toString()` to `ConstraintViolationInterface`
 * Add method `__toString()` to `ConstraintViolationListInterface`
 * Add method `disableTranslation()` to `ConstraintViolationBuilderInterface`
 * Remove static property `$errorNames` from all constraints, use const `ERROR_NAMES` instead
 * Remove static property `$versions` from the `Ip` constraint, use the `VERSIONS` constant instead
 * Remove `VALIDATION_MODE_LOOSE` from `Email` constraint, use `VALIDATION_MODE_HTML5` instead
 * Remove constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead
 * Remove Doctrine annotations support in favor of native attributes
 * Remove the annotation reader parameter from the constructor signature of `AnnotationLoader`
 * Remove `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Remove `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
 * Remove `ValidatorBuilder::enableAnnotationMapping()`, use `ValidatorBuilder::enableAttributeMapping()` instead
 * Remove `ValidatorBuilder::disableAnnotationMapping()`, use `ValidatorBuilder::disableAttributeMapping()` instead
 * Remove `AnnotationLoader`, use `AttributeLoader` instead

6.4
---

 * Add `is_valid` function to the `Expression` constraint, its behavior is the same as `ValidatorInterface::validate`
 * Allow single integer for the `versions` option of the `Uuid` constraint
 * Allow single constraint to be passed to the `constraints` option of the `When` constraint
 * Deprecate Doctrine annotations support in favor of native attributes
 * Deprecate `ValidatorBuilder::setDoctrineAnnotationReader()`
 * Deprecate `ValidatorBuilder::addDefaultDoctrineAnnotationReader()`
 * Add `number`, `finite-number` and `finite-float` types to `Type` constraint
 * Add the `withSeconds` option to the `Time` constraint that allows to pass time without seconds
 * Deprecate `ValidatorBuilder::enableAnnotationMapping()`, use `ValidatorBuilder::enableAttributeMapping()` instead
 * Deprecate `ValidatorBuilder::disableAnnotationMapping()`, use `ValidatorBuilder::disableAttributeMapping()` instead
 * Deprecate `AnnotationLoader`, use `AttributeLoader` instead
 * Add `GroupProviderInterface` to implement validation group providers outside the underlying class

6.3
---

 * Add method `getConstraint()` to `ConstraintViolationInterface`
 * Add `Uuid::TIME_BASED_VERSIONS` to match that a UUID being validated embeds a timestamp
 * Add the `pattern` parameter in violations of the `Regex` constraint
 * Add a `NoSuspiciousCharacters` constraint to validate a string is not a spoofing attempt
 * Add a `PasswordStrength` constraint to check the strength of a password
 * Add the `countUnit` option to the `Length` constraint to allow counting the string length either by code points (like before, now the default setting `Length::COUNT_CODEPOINTS`), bytes (`Length::COUNT_BYTES`) or graphemes (`Length::COUNT_GRAPHEMES`)
 * Add the `filenameMaxLength` option to the `File` constraint
 * Add the `exclude` option to the `Cascade` constraint
 * Add the `value_length` parameter to `Length` constraint
 * Allow to disable the translation domain for `ConstraintViolationInterface` messages

6.2
---

 * Add option `Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD` with "html5-allow-no-tld" e-mail validation mode, to match with the W3C official specification
 * Add method `getCause()` to `ConstraintViolationInterface`
 * Add the `When` constraint and validator
 * Deprecate the "loose" e-mail validation mode, use "html5" instead
 * Add the `negate` option to the `Expression` constraint, to inverse the logic of the violation's creation
 * Add the `extensions` option to the `File` constraint as an alternative to `mimeTypes` which checks the mime type of the file, its extension, and the consistency between them
 * Add padding for enhanced privacy to the `NotCompromisedPasswordValidator`

6.1
---

 * Add the `fields` option to the `Unique` constraint, to define which collection keys should be checked for uniqueness
 * Deprecate `Constraint::$errorNames`, use `Constraint::ERROR_NAMES` instead
 * Deprecate constraint `ExpressionLanguageSyntax`, use `ExpressionSyntax` instead
 * Add method `__toString()` to `ConstraintViolationInterface` & `ConstraintViolationListInterface`
 * Allow creating constraints with required arguments
 * Add the `match` option to the `Choice` constraint

6.0
---

 * Remove the `allowEmptyString` option from the `Length` constraint
 * Remove the `NumberConstraintTrait` trait
 * `ValidatorBuilder::enableAnnotationMapping()` does not accept a Doctrine annotation reader anymore
 * `ValidatorBuilder::enableAnnotationMapping()` won't automatically setup a Doctrine annotation reader anymore

5.4
---

 * Add a `Cidr` constraint to validate CIDR notations
 * Add a `CssColor` constraint to validate CSS colors
 * Add support for `ConstraintViolationList::createFromMessage()`
 * Add error's uid to `Count` and `Length` constraints with "exactly" option enabled

5.3
---

 * Add the `normalizer` option to the `Unique` constraint
 * Add `Validation::createIsValidCallable()` that returns true/false instead of throwing exceptions

5.2.0
-----

 * added a `Cascade` constraint to ease validating nested typed object properties
 * deprecated the `allowEmptyString` option of the `Length` constraint

   Before:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\Length(min=5, allowEmptyString=true)
    */
   ```

   After:

   ```php
   use Symfony\Component\Validator\Constraints as Assert;

   /**
    * @Assert\AtLeastOneOf({
    *     @Assert\Blank(),
    *     @Assert\Length(min=5)
    * })
    */
   ```
 * added the `Isin` constraint and validator
 * added the `ULID` constraint and validator
 * added support for UUIDv6 in `Uuid` constraint
 * enabled the validator to load constraints from PHP attributes
 * deprecated the `NumberConstraintTrait` trait
 * deprecated setting or creating a Doctrine annotation reader via `ValidatorBuilder::enableAnnotationMapping()`, pass `true` as first parameter and additionally call `setDoctrineAnnotationReader()` or `addDefaultDoctrineAnnotationReader()` to set up the annotation reader

5.1.0
-----

 * Add `AtLeastOneOf` constraint that is considered to be valid if at least one of the nested constraints is valid
 * added the `Hostname` constraint and validator
 * added the `alpha3` option to the `Country` and `Language` constraints
 * allow to define a reusable set of constraints by extending the `Compound` constraint
 * added `Sequentially` constraint, to sequentially validate a set of constraints (any violation raised will prevent further validation of the nested constraints)
 * added the `divisibleBy` option to the `Count` constraint
 * added the `ExpressionLanguageSyntax` constraint

5.0.0
-----

 * an `ExpressionLanguage` instance or null must be passed as the first argument of `ExpressionValidator::__construct()`
 * removed the `checkDNS` and `dnsMessage` options of the `Url` constraint
 * removed the `checkMX`, `checkHost` and `strict` options of the `Email` constraint
 * removed support for validating instances of `\DateTimeInterface` in `DateTimeValidator`, `DateValidator` and `TimeValidator`
 * removed support for using the `Bic`, `Country`, `Currency`, `Language` and `Locale` constraints without `symfony/intl`
 * removed support for using the `Email` constraint without `egulias/email-validator`
 * removed support for using the `Expression` constraint without `symfony/expression-language`
 * changed default value of `canonicalize` option of `Locale` constraint to `true`
 * removed `ValidatorBuilderInterface`
 * passing a null message when instantiating a `ConstraintViolation` is not allowed
 * changed the default value of `Length::$allowEmptyString` to `false` and made it optional
 * removed `Symfony\Component\Validator\Mapping\Cache\CacheInterface` in favor of PSR-6.
 * removed `ValidatorBuilder::setMetadataCache`, use `ValidatorBuilder::setMappingCache` instead.

4.4.0
-----

 * [BC BREAK] using null as `$classValidatorRegexp` value in `PropertyInfoLoader::__construct` will not enable auto-mapping for all classes anymore, use `'{.*}'` instead.
 * added `EnableAutoMapping` and `DisableAutoMapping` constraints to enable or disable auto mapping for class or a property
 * using anything else than a `string` as the code of a `ConstraintViolation` is deprecated, a `string` type-hint will
   be added to the constructor of the `ConstraintViolation` class and to the `ConstraintViolationBuilder::setCode()`
   method in 5.0
 * deprecated passing an `ExpressionLanguage` instance as the second argument of `ExpressionValidator::__construct()`. Pass it as the first argument instead.
 * added the `compared_value_path` parameter in violations when using any
   comparison constraint with the `propertyPath` option.
 * added support for checking an array of types in `TypeValidator`
 * added a new `allowEmptyString` option to the `Length` constraint to allow rejecting empty strings when `min` is set, by setting it to `false`.
 * Added new `minPropertyPath` and `maxPropertyPath` options
   to `Range` constraint in order to get the value to compare
   from an array or object
 * added the `min_limit_path` and `max_limit_path` parameters in violations when using
   `Range` constraint with respectively the `minPropertyPath` and
   `maxPropertyPath` options
 * added a new `notInRangeMessage` option to the `Range` constraint that will
   be used in the violation builder when both `min` and `max` are not null
 * added ability to use stringable objects as violation messages
 * Overriding the methods `ConstraintValidatorTestCase::setUp()` and `ConstraintValidatorTestCase::tearDown()` without the `void` return-type is deprecated.
 * deprecated `Symfony\Component\Validator\Mapping\Cache\CacheInterface` in favor of PSR-6.
 * deprecated `ValidatorBuilder::setMetadataCache`, use `ValidatorBuilder::setMappingCache` instead.
 * Marked the `ValidatorDataCollector` class as `@final`.

4.3.0
-----

 * added `Timezone` constraint
 * added `NotCompromisedPassword` constraint
 * added options `iban` and `ibanPropertyPath` to Bic constraint
 * added UATP cards support to `CardSchemeValidator`
 * added option `allowNull` to NotBlank constraint
 * added `Json` constraint
 * added `Unique` constraint
 * added a new `normalizer` option to the string constraints and to the `NotBlank` constraint
 * added `Positive` constraint
 * added `PositiveOrZero` constraint
 * added `Negative` constraint
 * added `NegativeOrZero` constraint

4.2.0
-----

 * added a new `UnexpectedValueException` that can be thrown by constraint validators, these exceptions are caught by
   the validator and are converted into constraint violations
 * added `DivisibleBy` constraint
 * decoupled from `symfony/translation` by using `Symfony\Contracts\Translation\TranslatorInterface`
 * deprecated `ValidatorBuilderInterface`
 * made `ValidatorBuilder::setTranslator()` final
 * marked `format` the default option in `DateTime` constraint
 * deprecated validating instances of `\DateTimeInterface` in `DateTimeValidator`, `DateValidator` and `TimeValidator`.
 * deprecated using the `Bic`, `Country`, `Currency`, `Language` and `Locale` constraints without `symfony/intl`
 * deprecated using the `Email` constraint without `egulias/email-validator`
 * deprecated using the `Expression` constraint without `symfony/expression-language`

4.1.0
-----

 * Deprecated the `checkDNS` and `dnsMessage` options of the `Url` constraint.
 * added a `values` option to the `Expression` constraint
 * Deprecated use of `Locale` constraint without setting `true` at "canonicalize" option, which will be the default value in 5.0

4.0.0
-----

 * Setting the `strict` option of the `Choice` constraint to anything but `true`
   is not supported anymore.
 * removed the `DateTimeValidator::PATTERN` constant
 * removed the `AbstractConstraintValidatorTest` class
 * removed support for setting the `checkDNS` option of the `Url` constraint to `true`

3.4.0
-----

 * added support for validation groups to the `Valid` constraint
 * not setting the `strict` option of the `Choice` constraint to `true` is
   deprecated and will throw an exception in Symfony 4.0
 * setting the `checkDNS` option of the `Url` constraint to `true` is deprecated in favor of
   the `Url::CHECK_DNS_TYPE_*` constants values and will throw an exception in Symfony 4.0
 * added min/max amount of pixels check to `Image` constraint via `minPixels` and `maxPixels`
 * added a new "propertyPath" option to comparison constraints in order to get the value to compare from an array or object

3.3.0
-----

 * added `AddValidatorInitializersPass`
 * added `AddConstraintValidatorsPass`
 * added `ContainerConstraintValidatorFactory`

3.2.0
-----

 * deprecated `Tests\Constraints\AbstractConstraintValidatorTest` in favor of `Test\ConstraintValidatorTestCase`
 * added support for PHP constants in YAML configuration files

3.1.0
-----

 * deprecated `DateTimeValidator::PATTERN` constant
 * added a `format` option to the `DateTime` constraint

2.8.0
-----

 * added the BIC (SWIFT-Code) validator

2.7.0
-----

 * deprecated `DefaultTranslator` in favor of `Symfony\Component\Translation\IdentityTranslator`
 * deprecated PHP7-incompatible constraints (Null, True, False) and related validators (NullValidator, TrueValidator, FalseValidator) in favor of their `Is`-prefixed equivalent

2.6.0
-----

 * [BC BREAK] `FileValidator` disallow empty files
 * [BC BREAK] `UserPasswordValidator` source message change
 * [BC BREAK] added internal `ExecutionContextInterface::setConstraint()`
 * added `ConstraintViolation::getConstraint()`
 * [BC BREAK] The `ExpressionValidator` will now evaluate the Expression even when the property value is null or an empty string
 * deprecated `ClassMetadata::hasMemberMetadatas()`
 * deprecated `ClassMetadata::getMemberMetadatas()`
 * deprecated `ClassMetadata::addMemberMetadata()`
 * [BC BREAK] added `Mapping\MetadataInterface::getConstraints()`
 * added generic "payload" option to all constraints for attaching domain-specific data
 * [BC BREAK] added `ConstraintViolationBuilderInterface::setCause()`

2.5.0
-----

 * deprecated `ApcCache` in favor of `DoctrineCache`
 * added `DoctrineCache` to adapt any Doctrine cache
 * `GroupSequence` now implements `ArrayAccess`, `Countable` and `Traversable`
 * [BC BREAK] changed `ClassMetadata::getGroupSequence()` to return a `GroupSequence` instance instead of an array
 * `Callback` can now be put onto properties (useful when you pass a closure to the constraint)
 * deprecated `ClassBasedInterface`
 * deprecated `MetadataInterface`
 * deprecated `PropertyMetadataInterface`
 * deprecated `PropertyMetadataContainerInterface`
 * deprecated `Mapping\ElementMetadata`
 * added `Mapping\MetadataInterface`
 * added `Mapping\ClassMetadataInterface`
 * added `Mapping\PropertyMetadataInterface`
 * added `Mapping\GenericMetadata`
 * added `Mapping\CascadingStrategy`
 * added `Mapping\TraversalStrategy`
 * deprecated `Mapping\ClassMetadata::accept()`
 * deprecated `Mapping\MemberMetadata::accept()`
 * removed array type hint of `Mapping\ClassMetadata::setGroupSequence()`
 * deprecated `MetadataFactoryInterface`
 * deprecated `Mapping\BlackholeMetadataFactory`
 * deprecated `Mapping\ClassMetadataFactory`
 * added `Mapping\Factory\MetadataFactoryInterface`
 * added `Mapping\Factory\BlackHoleMetadataFactory`
 * added `Mapping\Factory\LazyLoadingMetadataFactory`
 * deprecated `ExecutionContextInterface`
 * deprecated `ExecutionContext`
 * deprecated `GlobalExecutionContextInterface`
 * added `Context\ExecutionContextInterface`
 * added `Context\ExecutionContext`
 * added `Context\ExecutionContextFactoryInterface`
 * added `Context\ExecutionContextFactory`
 * deprecated `ValidatorInterface`
 * deprecated `Validator`
 * deprecated `ValidationVisitorInterface`
 * deprecated `ValidationVisitor`
 * added `Validator\ValidatorInterface`
 * added `Validator\RecursiveValidator`
 * added `Validator\ContextualValidatorInterface`
 * added `Validator\RecursiveContextualValidator`
 * added `Violation\ConstraintViolationBuilderInterface`
 * added `Violation\ConstraintViolationBuilder`
 * added `ConstraintViolation::getParameters()`
 * added `ConstraintViolation::getPlural()`
 * added `Constraints\Traverse`
 * deprecated `$deep` property in `Constraints\Valid`
 * added `ValidatorBuilderInterface::setApiVersion()`
 * added `Validation::API_VERSION_2_4`
 * added `Validation::API_VERSION_2_5`
 * added `Exception\OutOfBoundsException`
 * added `Exception\UnsupportedMetadataException`
 * made `Exception\ValidatorException` extend `Exception\RuntimeException`
 * added `Util\PropertyPath`
 * made the PropertyAccess component an optional dependency
 * deprecated `ValidatorBuilder::setPropertyAccessor()`
 * deprecated `validate` and `validateValue` on `Validator\Context\ExecutionContext` use `getValidator()` together with `inContext()` instead

2.4.0
-----

 * added a constraint the uses the expression language
 * added `minRatio`, `maxRatio`, `allowSquare`, `allowLandscape`, and `allowPortrait` to Image validator

2.3.29
------

 * fixed compatibility with PHP7 and up by introducing new constraints (IsNull, IsTrue, IsFalse) and related validators (IsNullValidator, IsTrueValidator, IsFalseValidator)

2.3.0
-----

 * added the ISBN, ISSN, and IBAN validators
 * copied the constraints `Optional` and `Required` to the
   `Symfony\Component\Validator\Constraints\` namespace and deprecated the original
   classes.
 * added comparison validators (EqualTo, NotEqualTo, LessThan, LessThanOrEqualTo, GreaterThan, GreaterThanOrEqualTo, IdenticalTo, NotIdenticalTo)

2.2.0
-----

 * added a CardScheme validator
 * added a Luhn validator
 * moved @api-tags from `Validator` to `ValidatorInterface`
 * moved @api-tags from `ConstraintViolation` to the new `ConstraintViolationInterface`
 * moved @api-tags from `ConstraintViolationList` to the new `ConstraintViolationListInterface`
 * moved @api-tags from `ExecutionContext` to the new `ExecutionContextInterface`
 * [BC BREAK] `ConstraintValidatorInterface::initialize` is now type hinted against `ExecutionContextInterface` instead of `ExecutionContext`
 * [BC BREAK] changed the visibility of the properties in `Validator` from protected to private
 * deprecated `ClassMetadataFactoryInterface` in favor of the new `MetadataFactoryInterface`
 * deprecated `ClassMetadataFactory::getClassMetadata` in favor of `getMetadataFor`
 * created `MetadataInterface`, `PropertyMetadataInterface`, `ClassBasedInterface` and `PropertyMetadataContainerInterface`
 * deprecated `GraphWalker` in favor of the new `ValidationVisitorInterface`
 * deprecated `ExecutionContext::addViolationAtPath`
 * deprecated `ExecutionContext::addViolationAtSubPath` in favor of `ExecutionContextInterface::addViolationAt`
 * deprecated `ExecutionContext::getCurrentClass` in favor of `ExecutionContextInterface::getClassName`
 * deprecated `ExecutionContext::getCurrentProperty` in favor of `ExecutionContextInterface::getPropertyName`
 * deprecated `ExecutionContext::getCurrentValue` in favor of `ExecutionContextInterface::getValue`
 * deprecated `ExecutionContext::getGraphWalker` in favor of `ExecutionContextInterface::validate` and `ExecutionContextInterface::validateValue`
 * improved `ValidatorInterface::validateValue` to accept arrays of constraints
 * changed `ValidatorInterface::getMetadataFactory` to return a `MetadataFactoryInterface` instead of a `ClassMetadataFactoryInterface`
 * removed `ClassMetadataFactoryInterface` type hint from `ValidatorBuilderInterface::setMetadataFactory`.
   As of Symfony 2.3, this method will be typed against `MetadataFactoryInterface` instead.
 * [BC BREAK] the switches `traverse` and `deep` in the `Valid` constraint and in `GraphWalker::walkReference`
   are ignored for arrays now. Arrays are always traversed recursively.
 * added dependency to Translation component
 * violation messages are now translated with a TranslatorInterface implementation
 * [BC BREAK] inserted argument `$message` in the constructor of `ConstraintViolation`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `ExecutionContext`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `GraphWalker`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `ValidationVisitor`
 * [BC BREAK] inserted arguments `$translator` and `$translationDomain` in the constructor of `Validator`
 * [BC BREAK] added `setTranslator()` and `setTranslationDomain()` to `ValidatorBuilderInterface`
 * improved the Validator to support pluralized messages by default
 * [BC BREAK] changed the source of all pluralized messages in the translation files to the pluralized version
 * added ExceptionInterface, BadMethodCallException and InvalidArgumentException

2.1.0
-----

 * added support for `ctype_*` assertions in `TypeValidator`
 * improved the ImageValidator with min width, max width, min height, and max height constraints
 * added support for MIME with wildcard in FileValidator
 * changed Collection validator to add "missing" and "extra" errors to
   individual fields
 * changed default value for `extraFieldsMessage` and `missingFieldsMessage`
   in Collection constraint
 * made ExecutionContext immutable
 * deprecated Constraint methods `setMessage`, `getMessageTemplate` and
   `getMessageParameters`
 * added support for dynamic group sequences with the GroupSequenceProvider pattern
 * [BC BREAK] ConstraintValidatorInterface method `isValid` has been renamed to
   `validate`, its return value was dropped. ConstraintValidator still contains
   `isValid` for BC
 * [BC BREAK] collections in fields annotated with `Valid` are not traversed
   recursively anymore by default. `Valid` contains a new property `deep`
   which enables the BC behavior.
 * added Count constraint
 * added Length constraint
 * added Range constraint
 * deprecated the Min and Max constraints
 * deprecated the MinLength and MaxLength constraints
 * added Validation and ValidatorBuilderInterface
 * deprecated ValidatorContext, ValidatorContextInterface and ValidatorFactory
