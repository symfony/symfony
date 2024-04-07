UPGRADE FROM 7.0 to 7.1
=======================

Symfony 7.1 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.1/setup/upgrade_minor.html).

If you're upgrading from a version below 7.0, follow the [7.0 upgrade guide](UPGRADE-7.0.md) first.

Table of Contents
-----------------

Bundles

 * [FrameworkBundle](#FrameworkBundle)
 * [SecurityBundle](#SecurityBundle)
 * [TwigBundle](#TwigBundle)

Bridges

 * [DoctrineBridge](#DoctrineBridge)

Components

 * [AssetMapper](#AssetMapper)
 * [Cache](#Cache)
 * [DependencyInjection](#DependencyInjection)
 * [ExpressionLanguage](#ExpressionLanguage)
 * [Form](#Form)
 * [Intl](#Intl)
 * [PropertyInfo](#PropertyInfo)
 * [Translation](#Translation)
 * [Workflow](#Workflow)

AssetMapper
-----------

 * Deprecate `ImportMapConfigReader::splitPackageNameAndFilePath()`, use `ImportMapEntry::splitPackageNameAndFilePath()` instead

Cache
-----

 * Deprecate `CouchbaseBucketAdapter`, use `CouchbaseCollectionAdapter` with Couchbase 3 instead

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

Form
----

 * Deprecate not configuring the `default_protocol` option of the `UrlType`, it will default to `null` in 8.0 (the current default is `'http'`)

FrameworkBundle
---------------

 * Mark classes `ConfigBuilderCacheWarmer`, `Router`, `SerializerCacheWarmer`, `TranslationsCacheWarmer`, `Translator` and `ValidatorCacheWarmer` as `final`
 * Deprecate the `router.cache_dir` config option, the Router will always use the `kernel.build_dir` parameter

Intl
----

 * [BC BREAK] Extracted `EmojiTransliterator` to a separate `symfony/emoji` component, the new FQCN is `Symfony\Component\Emoji\EmojiTransliterator`.
   You must install the `symfony/emoji` component if you're using the old `EmojiTransliterator` class in the Intl component.

Mailer
------

 * Postmark's "406 - Inactive recipient" API error code now results in a `PostmarkDeliveryEvent` instead of throwing a `HttpTransportException`

PropertyInfo
------------

 * Deprecate the `Type` class, use `Symfony\Component\TypeInfo\Type` class of `symfony/type-info` component instead

   *Before*
   ```php
   use Symfony\Component\PropertyInfo\Type;

   // bool
   $boolType = new Type(LegacyType::BUILTIN_TYPE_BOOL);
   // bool|null
   $nullableType = new Type(LegacyType::BUILTIN_TYPE_BOOL, nullable: true);
   // array<int, string|null>
   $arrayType = new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_STRING, true));

   $arrayType->getBuiltinType(); // returns "array"
   $arrayType->getCollectionKeyTypes(); // returns an array with an "int" Type instance
   $arrayType->getCollectionValueTypes()[0]->isNullable(); // returns true
   ```

   *After*
   ```php
   use Symfony\Component\TypeInfo\Type;

   // bool
   $boolType = Type::bool();
   // bool|null
   $nullableType = Type::nullable(Type::bool());
   // array<int, string|null>
   $arrayType = Type::array(Type::nullable(Type::string()), Type::int());

   (string) $arrayType->getBaseType(); // returns "array"
   $arrayType->getCollectionKeyType(); // returns an "int" Type instance
   $arrayType->getCollectionValueType()->isNullable(); // returns true
   ```

 * Deprecate `PropertyTypeExtractorInterface::getTypes()`, use `PropertyTypeExtractorInterface::getType()` instead

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
