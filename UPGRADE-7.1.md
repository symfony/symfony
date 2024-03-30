UPGRADE FROM 7.0 to 7.1
=======================

AssetMapper
-----------

 * Deprecate `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

Cache
-----

 * Deprecate `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` instead

ExpressionLanguage
------------------

 * Deprecate passing `null` as the allowed variable names to `ExpressionLanguage::lint()` and `Parser::lint()`,
   pass the `IGNORE_UNKNOWN_VARIABLES` flag instead to ignore unknown variables during linting

FrameworkBundle
---------------

 * Mark classes `ConfigBuilderCacheWarmer`, `Router`, `SerializerCacheWarmer`, `TranslationsCacheWarmer`, `Translator` and `ValidatorCacheWarmer` as `final`

Security
--------

 * Add method `getDecision()` to `AccessDecisionStrategyInterface`
 * Deprecate `AccessDecisionStrategyInterface::decide()` in favor of `AccessDecisionStrategyInterface::getDecision()`
 * Add method `getVote()` to `VoterInterface`
 * Deprecate `VoterInterface::vote()` in favor of `AccessDecisionStrategyInterface::getVote()`
 * Deprecate returning `bool` from `Voter::voteOnAttribute()` (it must return a `Vote`)
 * Add method `getDecision()` to `AccessDecisionManagerInterface`
 * Deprecate `AccessDecisionManagerInterface::decide()` in favor of `AccessDecisionManagerInterface::getDecision()`
 * Add method `getDecision()` to `AuthorizationCheckerInterface`
 * Add methods `setAccessDecision()` and `getAccessDecision()` to `AccessDeniedException`
 * Add method `getDecision()` to `Security`

SecurityBundle
--------------

 * Mark class `ExpressionCacheWarmer` as `final`

Translation
-----------

 * Mark class `DataCollectorTranslator` as `final`

TwigBundle
----------

 * Mark class `TemplateCacheWarmer` as `final`

Workflow
--------

 * Add method `getEnabledTransition()` to `WorkflowInterface`
 * Add `$nbToken` argument to `Marking::mark()` and `Marking::unmark()`
