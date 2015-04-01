CHANGELOG
=========

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
