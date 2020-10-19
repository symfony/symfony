CHANGELOG
=========

4.4.0
-----

 * [BC BREAK] using null as `$classValidatorRegexp` value in `DoctrineLoader::__construct` will not enable auto-mapping for all classes anymore, use `'{.*}'` instead.
 * added `DoctrineClearEntityManagerWorkerSubscriber`
 * deprecated `RegistryInterface`, use `Doctrine\Persistence\ManagerRegistry`
 * added support for invokable event listeners
 * added `getMetadataDriverClass` method to deprecate class parameters in service configuration files

4.3.0
-----

 * changed guessing of DECIMAL to set the `input` option of `NumberType` to string
 * deprecated not passing an `IdReader` to the `DoctrineChoiceLoader` when query can be optimized with a single id field
 * deprecated passing an `IdReader` to the `DoctrineChoiceLoader` when entities have a composite id
 * added two Messenger middleware: `DoctrinePingConnectionMiddleware` and `DoctrineCloseConnectionMiddleware`

4.2.0
-----

 * deprecated injecting `ClassMetadataFactory` in `DoctrineExtractor`,
   an instance of `EntityManagerInterface` should be injected instead
 * added support for `simple_array` type
 * the `DoctrineTransactionMiddlewareFactory` class has been removed

4.1.0
-----

 * added support for datetime immutable types in form type guesser

4.0.0
-----

 * the first constructor argument of the `DoctrineChoiceLoader` class must be
   an `ObjectManager` implementation
 * removed the `MergeDoctrineCollectionListener::onBind()` method
 * trying to reset a non-lazy manager service using the `ManagerRegistry::resetService()`
   method throws an exception
 * removed the `DoctrineParserCache` class

3.4.0
-----

 * added support for doctrine/dbal v2.6 types
 * added cause of UniqueEntity constraint violation
 * deprecated `DbalSessionHandler` and `DbalSessionHandlerSchema` in favor of
   `Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler`

3.1.0
-----

 * added "{{ value }}" message placeholder to UniqueEntityValidator
 * deprecated `MergeDoctrineCollectionListener::onBind` in favor of
   `MergeDoctrineCollectionListener::onSubmit`
 * deprecated passing `ChoiceListFactoryInterface` as first argument of
   `DoctrineChoiceLoader`'s constructor

3.0.0
-----

 * removed `EntityChoiceList`
 * removed `$manager` (2nd) and `$class` (3th) arguments of `ORMQueryBuilderLoader`
 * removed passing a query builder closure to `ORMQueryBuilderLoader`
 * removed `loader` and `property` options of the `DoctrineType`

2.8.0
-----

 * deprecated using the entity provider with a Doctrine repository implementing UserProviderInterface
 * added UserLoaderInterface for loading users through Doctrine.

2.7.0
-----

 * added DoctrineChoiceLoader
 * deprecated EntityChoiceList
 * deprecated passing a query builder closure to ORMQueryBuilderLoader
 * deprecated $manager and $em arguments of ORMQueryBuilderLoader
 * added optional arguments $propertyAccessor and $choiceListFactory to DoctrineOrmExtension constructor
 * deprecated "loader" and "property" options of DoctrineType

2.4.0
-----

 * deprecated DoctrineOrmTestCase class

2.2.0
-----

 * added an optional PropertyAccessorInterface parameter to DoctrineType,
   EntityType and EntityChoiceList

2.1.0
-----

 * added a default implementation of the ManagerRegistry
 * added a session storage for Doctrine DBAL
 * DoctrineOrmTypeGuesser now guesses "collection" for array Doctrine type
 * DoctrineType now caches its choice lists in order to improve performance
 * DoctrineType now uses ManagerRegistry::getManagerForClass() if the option "em" is not set
 * UniqueEntity validation constraint now accepts a "repositoryMethod" option that will be used to check for uniqueness instead of the default "findBy"
 * [BC BREAK] the DbalLogger::log() visibility has been changed from public to
   protected
