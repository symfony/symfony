UPGRADE FROM 7.0 to 7.1
=======================

AssetMapper
-----------

 * Deprecate `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

Cache
-----

 * Deprecate `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` instead

DependencyInjection
-------------------

 * [BC BREAK] When used in the `prependExtension()` method, the `ContainerConfigurator::import()` method now prepends the configuration instead of appending it

DoctrineBridge
--------------

 * Deprecated `DoctrineExtractor::getTypes()`, use `DoctrineExtractor::getType()` instead

ExpressionLanguage
------------------

 * Deprecate passing `null` as the allowed variable names to `ExpressionLanguage::lint()` and `Parser::lint()`,
   pass the `IGNORE_UNKNOWN_VARIABLES` flag instead to ignore unknown variables during linting

FrameworkBundle
---------------

 * Mark classes `ConfigBuilderCacheWarmer`, `Router`, `SerializerCacheWarmer`, `TranslationsCacheWarmer`, `Translator` and `ValidatorCacheWarmer` as `final`

PropertyInfo
------------

 * Deprecate `PropertyTypeExtractorInterface::getTypes()`, use `PropertyTypeExtractorInterface::getType()` instead

HttpKernel
----------

 * Deprecate `Extension::addAnnotatedClassesToCompile()` and related code infrastructure

SecurityBundle
--------------

 * Mark class `ExpressionCacheWarmer` as `final`

Translation
-----------

 * Mark class `DataCollectorTranslator` as `final`

TwigBundle
----------

 * Mark class `TemplateCacheWarmer` as `final`

Validator
---------

 * Deprecate not passing a value for the `requireTld` option to the `Url` constraint (the default value will become `true` in 8.0)
 * Deprecate `Bic::INVALID_BANK_CODE_ERROR`

Workflow
--------

 * Add method `getEnabledTransition()` to `WorkflowInterface`
 * Add `$nbToken` argument to `Marking::mark()` and `Marking::unmark()`
