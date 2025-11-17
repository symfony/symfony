UPGRADE FROM 7.4 to 8.0
=======================

Symfony 7.4 and Symfony 8.0 are released simultaneously at the end of November 2025. According to the Symfony
release process, both versions have the same features, but Symfony 8.0 doesn't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.0/setup/upgrade_major.html).

> [!NOTE]
> Symfony v8 requires PHP v8.4 or higher

AssetMapper
-----------

 * Remove `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

BrowserKit
----------

 * Remove `AbstractBrowser::useHtml5Parser()`; the native HTML5 parser is used unconditionally

Cache
-----

 * Remove `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` instead

Config
------

 * Remove support for accessing the internal scope of the loader in PHP config files, use only its public API instead
 * Add argument `$singular` to `NodeBuilder::arrayNode()`
 * Add argument `$info` to `ArrayNodeDefinition::canBeDisabled()` and `canBeEnabled()`
 * Ensure configuration nodes do not have both `isRequired()` and `defaultValue()`
 * Remove generation of fluent methods in config builders

Console
-------

 * The `AsCommand` attribute class is now `final`
 * Remove methods `Command::getDefaultName()` and `Command::getDefaultDescription()` in favor of the `#[AsCommand]` attribute

   *Before*
   ```php
   use Symfony\Component\Console\Command\Command;

   class CreateUserCommand extends Command
   {
       public static function getDefaultName(): ?string
       {
           return 'app:create-user';
       }

       public static function getDefaultDescription(): ?string
       {
           return 'Creates users';
       }

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\Console\Attribute\AsCommand;
   use Symfony\Component\Console\Command\Command;

   #[AsCommand('app:create-user', 'Creates users')]
   class CreateUserCommand
   {
       // ...
   }
   ```
 * Add argument `$finishedIndicator` to `ProgressIndicator::finish()`
 * Ensure closures set via `Command::setCode()` method have proper parameter and return types

   ```diff
   +use Symfony\Component\Console\Input\InputInterface;
   +use Symfony\Component\Console\Output\OutputInterface;

   -$command->setCode(function ($input, $output) {
   +$command->setCode(function (InputInterface $input, OutputInterface $output): int {
        // ...
   +
   +    return 0;
    });
   ```
 * Add method `isSilent()` to `OutputInterface`
 * Remove deprecated `Symfony\Component\Console\Application::add()` method in favor of `Symfony\Component\Console\Application::addCommand()`

   ```diff
    use Symfony\Component\Console\Application;

    $application = new Application();
   -$application->add(new CreateUserCommand());
   +$application->addCommand(new CreateUserCommand());
   ```

DependencyInjection
-------------------

 * Remove support for using `$this` or the loader's internal scope from PHP config files; use the `$loader` variable instead
 * Remove `ExtensionInterface::getXsdValidationBasePath()` and `getNamespace()` without alternatives, the XML configuration format is no longer supported
 * Add argument `$throwOnAbstract` to `ContainerBuilder::findTaggedResourceIds()`
 * Registering a service without a class when its id is a non-existing FQCN throws an error
 * Replace `#[TaggedIterator]` and `#[TaggedLocator]` attributes with `#[AutowireLocator]` and `#[AutowireIterator]`

   ```diff
   +use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
   -use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

    class MyService
    {
   -     public function __construct(#[TaggedIterator('app.my_tag')] private iterable $services) {}
   +     public function __construct(#[AutowireIterator('app.my_tag')] private iterable $services) {}
    }
   ```
 * Remove `!tagged` tag, use `!tagged_iterator` instead
 * Remove the `ContainerBuilder::getAutoconfiguredAttributes()` method, use `getAttributeAutoconfigurators()` instead to retrieve all the callbacks for a specific attribute class
 * Add argument `$target` to `ContainerBuilder::registerAliasForArgument()`
 * Remove support for the fluent PHP format for semantic configuration, instantiate builders inline with the config array as argument and return them instead:
   ```diff
   -return function (AcmeConfig $config) {
   -    $config->color('red');
   -}
   +return new AcmeConfig([
   +    'color' => 'red',
   +]);
   ```

DoctrineBridge
--------------

 * Remove the `DoctrineExtractor::getTypes()` method, use `DoctrineExtractor::getType()` instead

   ```diff
   -$types = $extractor->getTypes(Foo::class, 'property');
   +$type = $extractor->getType(Foo::class, 'property');
   ```
 * Remove support for auto-mapping Doctrine entities to controller arguments; use explicit mapping instead
 * Make `ProxyCacheWarmer` class `final`

DomCrawler
----------

 * Remove argument `$useHtml5Parser` of `Crawler`'s constructor; the native HTML5 parser is used unconditionally

ExpressionLanguage
------------------

 * Remove support for passing `null` as the allowed variable names to `ExpressionLanguage::lint()` and `Parser::lint()`,
   pass the `IGNORE_UNKNOWN_VARIABLES` flag instead to ignore unknown variables during linting

   ```diff
   -$expressionLanguage->lint($expression, null);
   +$expressionLanguage->lint($expression, [], ExpressionLanguage::IGNORE_UNKNOWN_VARIABLES);
   ```

Form
----

 * The `default_protocol` option in `UrlType` now defaults to `null` instead of `'http'`

   *Before*
   ```php
   // URLs without protocol were automatically prefixed with 'http://'
   $builder->add('website', UrlType::class);
   // Input: 'example.com' → Value: 'http://example.com'
   ```

   *After*
   ```php
   // URLs without protocol are now kept as-is
   $builder->add('website', UrlType::class);
   // Input: 'example.com' → Value: 'example.com'

   // To restore the previous behavior, explicitly set the option:
   $builder->add('website', UrlType::class, [
       'default_protocol' => 'http',
   ]);
   ```
 * Made `ResizeFormListener::postSetData()` method `final`
 * Remove the `VersionAwareTest` trait, use feature detection instead
 * Remove deprecated `ResizeFormListener::preSetData()` method, use `postSetData()` instead
 * Remove `validation.xml` in `Resources/config`, replaced by attributes on the `Form` class

FrameworkBundle
---------------

 * Remove the `WorkflowDumpCommand` class; the `workflow:dump` command and its class were moved to the Workflow component, but the command still works as before
 * Remove `errors.xml` and `webhook.xml` routing configuration files (use their PHP equivalent instead)
 * Make `Router` class `final`
 * Make `SerializerCacheWarmer` class `final`
 * Make `Translator` class `final`
 * Make `ConfigBuilderCacheWarmer` class `final`
 * Make `TranslationsCacheWarmer` class `final`
 * Make `ValidatorCacheWarmer` class `final`
 * Remove autowiring aliases for `RateLimiterFactory`; use `RateLimiterFactoryInterface` instead
 * Remove `session.sid_length` and `session.sid_bits_per_character` config options
 * Remove the `router.cache_dir` config option
 * Remove the `validation.cache` option
 * Remove `TranslationUpdateCommand` in favor of `TranslationExtractCommand`
 * Remove deprecated `--show-arguments` option from `debug:container` command

HtmlSanitizer
-------------

 * Remove `MastermindsParser`; use `NativeParser` instead
 * Add argument `$context` to `ParserInterface::parse()`

HttpFoundation
--------------

 * Drop HTTP method override support for methods GET, HEAD, CONNECT and TRACE
 * Add argument `$subtypeFallback` to `Request::getFormat()`
 * Remove the following deprecated session options from `NativeSessionStorage`: `referer_check`, `use_only_cookies`, `use_trans_sid`, `sid_length`, `sid_bits_per_character`, `trans_sid_hosts`, `trans_sid_tags`
 * Trigger PHP warning when using `Request::sendHeaders()` after headers have already been sent; use a `StreamedResponse` instead
 * Add arguments `$v4Bytes` and `$v6Bytes` to `IpUtils::anonymize()`
 * Add argument `$partitioned` to `ResponseHeaderBag::clearCookie()`
 * Add argument `$expiration` to `UriSigner::sign()`
 * Remove `Request::get()`, use properties `->attributes`, `query` or `request` directly instead
 * Remove accepting null `$format` argument to `Request::setFormat()`

HttpClient
----------

 * Remove support for passing an instance of `StoreInterface` as `$cache` argument to `CachingHttpClient` constructor, use a `TagAwareCacheInterface` instead
 * Remove support for amphp/http-client < 5
 * Remove setLogger() methods on decorators; configure the logger on the wrapped client directly instead

HttpKernel
----------

 * Remove `AddAnnotatedClassesToCachePass`
 * Remove `Extension::getAnnotatedClassesToCompile()` and `Extension::addAnnotatedClassesToCompile()`
 * Remove `Kernel::getAnnotatedClassesToCompile()` and `Kernel::setAnnotatedClassCache()`
 * Make `ServicesResetter` class `final`
 * Add argument `$logChannel` to `ErrorListener::logException()`
 * Add argument `$event` to `DumpListener::configure()`
 * Replace `__sleep/wakeup()` by `__(un)serialize()` on kernels and data collectors
 * Add method `getShareDir()` to `KernelInterface`

Intl
----

 * Remove `Symfony\Component\Intl\Transliterator\EmojiTransliterator`, use `Symfony\Component\Emoji\EmojiTransliterator` instead

JsonStreamer
------------

 * Remove `$streamToNativeValueTransformers` argument of `PropertyMetadata::__construct()`, use `$valueTransformer` instead
 * Remove `PropertyMetadata::getNativeToStreamValueTransformer()` and `PropertyMetadata::getStreamToNativeValueTransformers()`, use `PropertyMetadata::getValueTransformers()` instead
 * Remove `PropertyMetadata::withNativeToStreamValueTransformers()` and `PropertyMetadata::withStreamToNativeValueTransformers()`, use `PropertyMetadata::withValueTransformers()` instead
 * Remove `PropertyMetadata::withAdditionalNativeToStreamValueTransformer()` and `PropertyMetadata::withAdditionalStreamToNativeValueTransformer`, use `PropertyMetadata::withAdditionalValueTransformer()` instead

Ldap
----

 * Remove the `sizeLimit` option of `AbstractQuery`
 * Remove `LdapUser::eraseCredentials()` in favor of `__serialize()`
 * Add methods for `saslBind()` and `whoami()` to `ConnectionInterface` and `LdapInterface`

Mailer
------

 * Remove `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

Messenger
---------

 * Remove `text` format when using the `messenger:stats` command
 * Add method `getRetryDelay()` to `RecoverableExceptionInterface`

Mime
----

 * Replace `__sleep/wakeup()` by `__(un)serialize()` on `AbstractPart` implementations

MonologBridge
-------------

 * Remove `NotFoundActivationStrategy`, use `HttpCodeActivationStrategy` instead

Notifier
--------

 * Remove the Sms77 Notifier bridge
 * Remove `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead.
   To keep using the `testIncompleteDsnException()` and `testMissingRequiredOptionException()` tests, you now need to use `IncompleteDsnTestTrait` or `MissingRequiredOptionTestTrait` respectively.

OptionsResolver
---------------

 * Remove support for nested options definition via `setDefault()`, use `setOptions()` instead

   ```diff
   -$resolver->setDefault('option', function (OptionsResolver $resolver) {
   +$resolver->setOptions('option', function (OptionsResolver $resolver) {
        // ...
    });
   ```

PropertyInfo
------------

 * Remove the `PropertyTypeExtractorInterface::getTypes()` method, use `PropertyTypeExtractorInterface::getType()` instead

   ```diff
   -$types = $extractor->getTypes(Foo::class, 'property');
   +$type = $extractor->getType(Foo::class, 'property');
   ```
 * Remove the `ConstructorArgumentTypeExtractorInterface::getTypesFromConstructor()` method, use `ConstructorArgumentTypeExtractorInterface::getTypeFromConstructor()` instead

   ```diff
   -$types = $extractor->getTypesFromConstructor(Foo::class, 'property');
   +$type = $extractor->getTypeFromConstructor(Foo::class, 'property');
   ```
 * Remove the `Type` class, use `Symfony\Component\TypeInfo\Type` class from `symfony/type-info` instead

   *Before*
   ```php
   use Symfony\Component\PropertyInfo\Type;

   // create types
   $int = [new Type(Type::BUILTIN_TYPE_INT)];
   $nullableString = [new Type(Type::BUILTIN_TYPE_STRING, true)];
   $object = [new Type(Type::BUILTIN_TYPE_OBJECT, false, Foo::class)];
   $boolList = [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(BUILTIN_TYPE_INT), new Type(BUILTIN_TYPE_BOOL))];
   $union = [new Type(Type::BUILTIN_TYPE_STRING), new Type(BUILTIN_TYPE_INT)];
   $intersection = [new Type(Type::BUILTIN_TYPE_OBJECT, false, \Traversable::class), new Type(Type::BUILTIN_TYPE_OBJECT, false, \Stringable::class)];

   // test if a type is nullable
   $intIsNullable = $int[0]->isNullable();

   // echo builtin types of union
   foreach ($union as $type) {
       echo $type->getBuiltinType();
   }

   // test if a type represents an instance of \ArrayAccess
   if ($object[0]->getClassName() instanceof \ArrayAccess::class) {
       // ...
   }

   // handle collections
   if ($boolList[0]->isCollection()) {
       $k = $boolList->getCollectionKeyTypes();
       $v = $boolList->getCollectionValueTypes();

       // ...
   }
   ```

   *After*
   ```php
   use Symfony\Component\TypeInfo\BuiltinType;
   use Symfony\Component\TypeInfo\CollectionType;
   use Symfony\Component\TypeInfo\Type;

   // create types
   $int = Type::int();
   $nullableString = Type::nullable(Type::string());
   $object = Type::object(Foo::class);
   $boolList = Type::list(Type::bool());
   $union = Type::union(Type::string(), Type::int());
   $intersection = Type::intersection(Type::object(\Traversable::class), Type::object(\Stringable::class));

   // test if a type is nullable
   $intIsNullable = $int->isNullable();

   // echo builtin types of union
   foreach ($union->traverse() as $type) {
       if ($type instanceof BuiltinType) {
           echo $type->getTypeIdentifier()->value;
       }
   }

   // test if a type represents an instance of \ArrayAccess
   if ($object->isIdentifiedBy(\ArrayAccess::class)) {
       // ...
   }

   // handle collections
   if ($boolList instanceof CollectionType) {
       $k = $boolList->getCollectionKeyType();
       $v = $boolList->getCollectionValueType();

       // ...
   }
   ```

Routing
-------

 * Remove support for accessing the internal scope of the loader in PHP config files, use only its public API instead
 * Providing a non-array `_query` parameter to `UrlGenerator` causes an `InvalidParameterException`
 * Remove the protected `AttributeClassLoader::$routeAnnotationClass` property and the `setRouteAnnotationClass()` method, use `AttributeClassLoader::setRouteAttributeClass()` instead
 * Remove class aliases in the `Annotation` namespace, use attributes instead
 * Remove getters and setters in attribute classes in favor of public properties

Security
--------

 * When extending the `RememberMeDetails` class and overriding its constructor, the `$userFqcn` parameter has to be removed from its signature:

   *Before*

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userFqcn, $userIdentifier, $expires, $value);
       }
   }
   ```

   *After*

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userIdentifier, $expires, $value);
       }
   }
   ```
 * Add argument `$accessDecision` to `AccessDecisionStrategyInterface::decide()`
 * Remove `PersistentTokenInterface::getClass()` and `RememberMeDetails::getUserFqcn()`
 * Remove the user FQCN from the remember-me cookie
 * Remove `UserInterface::eraseCredentials()` and `TokenInterface::eraseCredentials()`;
   erase credentials e.g. using `__serialize()` instead:

  ```diff
  -public function eraseCredentials(): void
  -{
  -}
  +// If your eraseCredentials() method was used to empty a "password" property:
  +public function __serialize(): array
  +{
  +    $data = (array) $this;
  +    unset($data["\0".self::class."\0password"]);
  +
  +    return $data;
  +}
  ```
 * Throw a `BadCredentialsException` when passing an empty string as `$userIdentifier` argument to `UserBadge` constructor
 * Accept only `ExposeSecurityLevel` enums for `AuthenticatorManager`'s `$exposeSecurityErrors` argument
 * Respectively accept only `AlgorithmManager` and `JWKSet` for `OidcTokenHandler`'s `$signatureAlgorithm` and `$signatureKeyset` arguments
 * Remove callable firewall listeners support, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Remove `AbstractListener::__invoke`
 * Remove `LazyFirewallContext::__invoke()`
 * Remove `RememberMeToken::getSecret()`
 * Add argument `$accessDecision` to `AccessDecisionManagerInterface::decide()` and `AuthorizationCheckerInterface::isGranted()`
 * Add argument `$vote` to `VoterInterface::vote()` and `Voter::voteOnAttribute()`
 * Add argument `$token` to `UserCheckerInterface::checkPostAuth()`
 * Add argument `$attributes` to `UserAuthenticatorInterface::authenticateUser()`
 * Make `UserChainProvider` implement `AttributesBasedUserProviderInterface`

SecurityBundle
--------------

 * Remove the deprecated `hide_user_not_found` configuration option, use `expose_security_errors` instead

   ```diff
    # config/packages/security.yaml
    security:
   -    hide_user_not_found: false
   +    expose_security_errors: 'all'
   ```

   ```diff
    # config/packages/security.yaml
    security:
   -    hide_user_not_found: true
   +    expose_security_errors: 'none'
   ```

   Note: The `expose_security_errors` option accepts three values:
   - `'none'`: Equivalent to `hide_user_not_found: true` (hides all security-related errors)
   - `'all'`: Equivalent to `hide_user_not_found: false` (exposes all security-related errors)
   - `'account_status'`: A new option that only exposes account status errors (e.g., account locked, disabled)

 * Make `ExpressionCacheWarmer` class `final`
 * Remove the deprecated `algorithm` and `key` options from the OIDC token handler configuration, use `algorithms` and `keyset` instead

   ```diff
    # config/packages/security.yaml
    security:
        firewalls:
            main:
                access_token:
                    token_handler:
                        oidc:
   -                        algorithm: 'RS256'
   -                        key: 'https://example.com/.well-known/jwks.json'
   +                        algorithms: ['RS256']
   +                        keyset: 'https://example.com/.well-known/jwks.json'
   ```
 * Remove autowiring aliases for `RateLimiterFactory`; use `RateLimiterFactoryInterface` instead

Serializer
----------

 * Remove escape character functionality from `CsvEncoder`

   ```diff
    use Symfony\Component\Serializer\Encoder\CsvEncoder;

    // Using escape character in encoding
    $encoder = new CsvEncoder();
   -$csv = $encoder->encode($data, 'csv', [
   -    CsvEncoder::ESCAPE_CHAR_KEY => '\\',
   -]);
   +$csv = $encoder->encode($data, 'csv');

    // Using escape character with context builder
    use Symfony\Component\Serializer\Context\Encoder\CsvEncoderContextBuilder;

    $context = (new CsvEncoderContextBuilder())
   -    ->withEscapeChar('\\')
        ->toArray();
   ```

 * Remove `AbstractNormalizerContextBuilder::withDefaultContructorArguments()`, use `withDefaultConstructorArguments()` instead
 * Change signature of `NameConverterInterface::normalize()` and `NameConverterInterface::denormalize()` methods:

   ```diff
   -public function normalize(string $propertyName): string;
   -public function denormalize(string $propertyName): string;
   +public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;
   +public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;
   ```
 * Remove `AdvancedNameConverterInterface`, use `NameConverterInterface` instead
 * Remove `ClassMetadataFactoryCompiler`, `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer`
 * Remove class aliases in the `Annotation` namespace, use attributes instead
 * Remove getters in attribute classes in favor of public properties

Translation
-----------

 * Remove the `$escape` parameter from `CsvFileLoader::setCsvControl()`

   ```diff
    use Symfony\Component\Translation\Loader\CsvFileLoader;

    $loader = new CsvFileLoader();

    // Set CSV control characters including escape character
   -$loader->setCsvControl(';', '"', '\\');
   +$loader->setCsvControl(';', '"');
   ```
 * Remove `TranslatableMessage::__toString()` method, use `trans()` or `getMessage()` instead
 * Make `DataCollectorTranslator` class `final`
 * Remove `ProviderFactoryTestCase`, extend `AbstractProviderFactoryTestCase` instead

String
------

 * Replace `__sleep/wakeup()` by `__(un)serialize()` on string implementations

TwigBridge
----------

 * Remove support for passing a tag to the constructor of `FormThemeNode`
 * Remove `text` format from the `debug:twig` command, use the `txt` format instead

TwigBundle
----------

 * Make `TemplateCacheWarmer` class `final`
 * Remove the `base_template_class` config option

TypeInfo
--------

 * Constructing a `CollectionType` instance as a list that is not an array throws an `InvalidArgumentException`
 * Remove the third `$asList` argument of `TypeFactoryTrait::iterable()`, use `TypeFactoryTrait::list()` instead

   ```diff
    use Symfony\Component\TypeInfo\Type;

   -$type = Type::iterable(Type::string(), asList: true);
   +$type = Type::list(Type::string());
   ```

Uid
---

 * Add argument `$format` to `Uuid::isValid()`

Validator
---------

 * Remove support for configuring constraint options implicitly with the XML format

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
 * Remove support for configuring constraint options implicitly with the YAML format

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
 * Remove support for passing associative arrays to `GroupSequence`

   *Before*

   ```php
   $groupSequence = GroupSequence(['value' => ['group 1', 'group 2']]);
   ```

   *After*

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

VarExporter
-----------

 * Restrict `ProxyHelper::generateLazyProxy()` to generating abstraction-based lazy decorators; use native lazy proxies otherwise
 * Remove `LazyGhostTrait` and `LazyProxyTrait`, use native lazy objects instead
 * Remove `ProxyHelper::generateLazyGhost()`, use native lazy objects instead

Webhook
-------

 * Add argument `$request` to `RequestParserInterface::createSuccessfulResponse()` and `RequestParserInterface::createRejectedResponse()`

WebProfilerBundle
-----------------

 * Remove `profiler.xml` and `wdt.xml` routing configuration files (use their PHP equivalent instead)

Workflow
--------

 * Add method `getEnabledTransition()` to `WorkflowInterface`
 * Add `$nbToken` argument to `Marking::mark()` and `Marking::unmark()`
 * Add `$asArc` argument to `Transition::getFroms()` and `Transition::getTos()`
 * Remove `Event::getWorkflow()` method

   *Before*
   ```php
   use Symfony\Component\Workflow\Attribute\AsCompletedListener;
   use Symfony\Component\Workflow\Event\CompletedEvent;

   class MyListener
   {
       #[AsCompletedListener('my_workflow', 'to_state2')]
       public function terminateOrder(CompletedEvent $event): void
       {
           $subject = $event->getSubject();
           if ($event->getWorkflow()->can($subject, 'to_state3')) {
               $event->getWorkflow()->apply($subject, 'to_state3');
           }
       }
   }
   ```

   *After*
   ```php
   use Symfony\Component\DependencyInjection\Attribute\Target;
   use Symfony\Component\Workflow\Attribute\AsCompletedListener;
   use Symfony\Component\Workflow\Event\CompletedEvent;
   use Symfony\Component\Workflow\WorkflowInterface;

   class MyListener
   {
       public function __construct(
           #[Target('my_workflow')]
           private readonly WorkflowInterface $workflow,
       ) {
       }

       #[AsCompletedListener('my_workflow', 'to_state2')]
       public function terminateOrder(CompletedEvent $event): void
       {
           $subject = $event->getSubject();
           if ($this->workflow->can($subject, 'to_state3')) {
               $this->workflow->apply($subject, 'to_state3');
           }
       }
   }
   ```

   *Or*
   ```php
   use Symfony\Component\DependencyInjection\ServiceLocator;
   use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
   use Symfony\Component\Workflow\Attribute\AsTransitionListener;
   use Symfony\Component\Workflow\Event\TransitionEvent;

   class GenericListener
   {
       public function __construct(
           #[AutowireLocator('workflow', 'name')]
           private ServiceLocator $workflows
       ) {
       }

       #[AsTransitionListener]
       public function doSomething(TransitionEvent $event): void
       {
           $workflow = $this->workflows->get($event->getWorkflowName());
       }
   }
   ```

Yaml
----

 * Remove support for parsing duplicate mapping keys whose value is `null`
