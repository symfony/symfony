CHANGELOG
=========

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
