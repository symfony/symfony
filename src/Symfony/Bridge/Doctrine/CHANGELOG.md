CHANGELOG
=========

2.1.5
-----

 * fixed caching of choice lists when EntityType is used with the "query_builder" option

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
