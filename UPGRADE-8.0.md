UPGRADE FROM 7.4 to 8.0
=======================

Symfony 7.4 and Symfony 8.0 are released simultaneously at the end of November 2025. According to the Symfony
release process, both versions have the same features, but Symfony 8.0 doesn't include any deprecated features.
To upgrade, make sure to resolve all deprecation notices.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.0/setup/upgrade_major.html).

AssetMapper
-----------

 * Remove `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

Console
-------

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

 * Ensure closures set via `Command::setCode()` method have proper parameter and return types

   *Before*
   ```php
   $command->setCode(function ($input, $output) {
       // ...
   });
   ```

   *After*
   ```php
   use Symfony\Component\Console\Input\InputInterface;
   use Symfony\Component\Console\Output\OutputInterface;

   $command->setCode(function (InputInterface $input, OutputInterface $output): int {
       // ...

       return 0;
   });
   ```

 * Add method `isSilent()` to `OutputInterface`

 * Remove deprecated `Symfony\Component\Console\Application::add()` method in favor of `Symfony\Component\Console\Application::addCommand()`

   *Before*
   ```php
   use Symfony\Component\Console\Application;

   $application = new Application();
   $application->add(new CreateUserCommand());
   ```

   *After*
   ```php
   use Symfony\Component\Console\Application;

   $application = new Application();
   $application->addCommand(new CreateUserCommand());
   ```

DependencyInjection
-------------------

 * Replace `#[TaggedIterator]` and `#[TaggedLocator]` attributes with `#[AutowireLocator]` and `#[AutowireIterator]`

    *Before*
    ```php
    use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

    class MyService
    {
         public function __construct(#[TaggedIterator('app.my_tag')] private iterable $services) {}
    }
    ```

    *After*
    ```php
    use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

    class MyService
    {
         public function __construct(#[AutowireIterator('app.my_tag')] private iterable $services) {}
    }
    ```

 * Remove the `ContainerBuilder::getAutoconfiguredAttributes()` method, use `getAttributeAutoconfigurators()` instead to retrieve all the callbacks for a specific attribute class

DoctrineBridge
--------------

 * Remove the `DoctrineExtractor::getTypes()` method, use `DoctrineExtractor::getType()` instead

   *Before*
   ```php
   $types = $extractor->getTypes(Foo::class, 'property');
   ```

   *After*
   ```php
   $type = $extractor->getType(Foo::class, 'property');
   ```

FrameworkBundle
---------------

 * Remove deprecated `Symfony\Bundle\FrameworkBundle\Console\Application::add()` method in favor of `Symfony\Bundle\FrameworkBundle\Console\Application::addCommand()`

   *Before*
   ```php
   use Symfony\Bundle\FrameworkBundle\Console\Application;

   $application = new Application($kernel);
   $application->add(new CreateUserCommand());
   ```

   *After*
   ```php
   use Symfony\Bundle\FrameworkBundle\Console\Application;

   $application = new Application($kernel);
   $application->addCommand(new CreateUserCommand());
   ```

HttpClient
----------

 * Remove support for amphp/http-client < 5
 * Remove setLogger() methods on decorators; configure the logger on the wrapped client directly instead

HttpKernel
----------

 * Remove `AddAnnotatedClassesToCachePass`
 * Remove `Extension::getAnnotatedClassesToCompile()` and `Extension::addAnnotatedClassesToCompile()`
 * Remove `Kernel::getAnnotatedClassesToCompile()` and `Kernel::setAnnotatedClassCache()`

Ldap
----

 * Remove `LdapUser::eraseCredentials()` in favor of `__serialize()`

Mailer
------

 * Remove `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

Notifier
--------

 * Remove the Sms77 Notifier bridge

OptionsResolver
---------------

 * Remove support for nested options definition via `setDefault()`, use `setOptions()` instead

   *Before*
   ```php
   $resolver->setDefault('option', function (OptionsResolver $resolver) {
       // ...
   });
   ```

   *After*
   ```php
   $resolver->setOptions('option', function (OptionsResolver $resolver) {
       // ...
   });
   ```

PropertyInfo
------------

 * Remove the `PropertyTypeExtractorInterface::getTypes()` method, use `PropertyTypeExtractorInterface::getType()` instead

   *Before*
   ```php
   $types = $extractor->getTypes(Foo::class, 'property');
   ```

   *After*
   ```php
   $type = $extractor->getType(Foo::class, 'property');
   ```

 * Remove the `ConstructorArgumentTypeExtractorInterface::getTypesFromConstructor()` method, use `ConstructorArgumentTypeExtractorInterface::getTypeFromConstructor()` instead

   *Before*
   ```php
   $types = $extractor->getTypesFromConstructor(Foo::class, 'property');
   ```

   *After*
   ```php
   $type = $extractor->getTypeFromConstructor(Foo::class, 'property');
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

 * Providing a non-array `_query` parameter to `UrlGenerator` causes an `InvalidParameterException`
 * Remove the protected `AttributeClassLoader::$routeAnnotationClass` property and the `setRouteAnnotationClass()` method, use `AttributeClassLoader::setRouteAttributeClass()` instead

Security
--------

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

 * Remove callable firewall listeners support, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Remove `AbstractListener::__invoke`
 * Remove `LazyFirewallContext::__invoke()`

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

   Before:

   ```php
   public function normalize(string $propertyName): string;
   public function denormalize(string $propertyName): string;
   ```

   After:

   ```php
   public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;
   public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string;
   ```
 * Remove `AdvancedNameConverterInterface`, use `NameConverterInterface` instead
 * Remove the `CompiledClassMetadataFactory` and `CompiledClassMetadataCacheWarmer` classes

TwigBridge
----------

 * Remove `text` format from the `debug:twig` command, use the `txt` format instead

VarExporter
-----------

 * Restrict `ProxyHelper::generateLazyProxy()` to generating abstraction-based lazy decorators; use native lazy proxies otherwise
 * Remove `LazyGhostTrait` and `LazyProxyTrait`, use native lazy objects instead
 * Remove `ProxyHelper::generateLazyGhost()`, use native lazy objects instead

Yaml
----

 * Remove support for parsing duplicate mapping keys whose value is `null`
