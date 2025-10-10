UPGRADE FROM 7.1 to 7.2
=======================

Symfony 7.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.2/setup/upgrade_minor.html).

If you're upgrading from a version below 7.1, follow the [7.1 upgrade guide](UPGRADE-7.1.md) first.

Table of Contents
-----------------

Bundles

 * [FrameworkBundle](#FrameworkBundle)

Bridges

 * [TwigBridge](#TwigBridge)

Components

 * [Cache](#Cache)
 * [Console](#Console)
 * [DependencyInjection](#DependencyInjection)
 * [Form](#Form)
 * [HttpFoundation](#HttpFoundation)
 * [Ldap](#Ldap)
 * [Lock](#Lock)
 * [Mailer](#Mailer)
 * [Notifier](#Notifier)
 * [Routing](#Routing)
 * [Security](#Security)
 * [Serializer](#Serializer)
 * [Translation](#Translation)
 * [Webhook](#Webhook)
 * [Yaml](#Yaml)

Cache
-----

 * `igbinary_serialize()` is no longer used instead of `serialize()` when the igbinary extension is installed, due to behavior
   incompatibilities between the two (performance might be impacted)

Console
-------

 * [BC BREAK] Add ``--silent`` global option to enable the silent verbosity mode (suppressing all output, including errors)
   If a custom command defines the `silent` option, it must be renamed before upgrading.
 * Add `isSilent()` method to `OutputInterface`

DependencyInjection
-------------------

 * Deprecate `!tagged` Yaml tag, use `!tagged_iterator` instead

   *Before*
   ```yaml
   services:
       App\Handler:
           tags: ['app.handler']

       App\HandlerCollection:
           arguments: [!tagged app.handler]
   ```

   *After*
   ```yaml
   services:
       App\Handler:
           tags: ['app.handler']

       App\HandlerCollection:
           arguments: [!tagged_iterator app.handler]
   ```

Form
----

 * Deprecate the `VersionAwareTest` trait, use feature detection instead

FrameworkBundle
---------------

 * [BC BREAK] The `secrets:decrypt-to-local` command terminates with a non-zero exit code when a secret could not be read
 * Deprecate making `cache.app` adapter taggable, use the `cache.app.taggable` adapter instead
 * Deprecate `session.sid_length` and `session.sid_bits_per_character` config options, following the deprecation of these options in PHP 8.4.

HttpFoundation
--------------

 * Deprecate passing `referer_check`, `use_only_cookies`, `use_trans_sid`, `trans_sid_hosts`, `trans_sid_tags`, `sid_bits_per_character` and `sid_length` options to `NativeSessionStorage`

Ldap
----

 * Deprecate the `sizeLimit` option of `AbstractQuery`, the option is unused

Lock
----

 * `RedisStore` uses `EVALSHA` over `EVAL` when evaluating LUA scripts

Mailer
------

* Deprecate `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

  The `testIncompleteDsnException()` test is no longer provided by default. If you make use of it by implementing the `incompleteDsnProvider()` data providers,
  you now need to use the `IncompleteDsnTestTrait`.

Notifier
--------

 * Deprecate `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

   The `testIncompleteDsnException()` and `testMissingRequiredOptionException()` tests are no longer provided by default. If you make use of them (i.e. by implementing the
   `incompleteDsnProvider()` or `missingRequiredOptionProvider()` data providers), you now need to use the `IncompleteDsnTestTrait` or `MissingRequiredOptionTestTrait` respectively.

Routing
-------

 * Deprecate the `AttributeClassLoader::$routeAnnotationClass` property, use `AttributeClassLoader::setRouteAttributeClass()` instead

Security
--------

 * Deprecate argument `$secret` of `RememberMeToken` and `RememberMeAuthenticator`, the argument is unused
 * Deprecate passing an empty string as `$userIdentifier` argument to `UserBadge` constructor
 * Deprecate returning an empty string in `UserInterface::getUserIdentifier()`

Serializer
----------

 * Deprecate the `csv_escape_char` context option of `CsvEncoder`, the `CsvEncoder::ESCAPE_CHAR_KEY` constant
   and the `CsvEncoderContextBuilder::withEscapeChar()` method, following its deprecation in PHP 8.4
 * Deprecate `AdvancedNameConverterInterface`, use `NameConverterInterface` instead

Translation
-----------

 * Deprecate `ProviderFactoryTestCase`, extend `AbstractProviderFactoryTestCase` instead

   The `testIncompleteDsnException()` test is no longer provided by default. If you make use of it by implementing the `incompleteDsnProvider()` data providers,
   you now need to use the `IncompleteDsnTestTrait`.

 * Deprecate passing an escape character to `CsvFileLoader::setCsvControl()`, following its deprecation in PHP 8.4

TwigBridge
----------

 * Deprecate passing a tag to the constructor of `FormThemeNode`

TypeInfo
--------

 * Rename `Type::isA()` to `Type::isIdentifiedBy()` and `Type::is()` to `Type::isSatisfiedBy()`
 * Remove `Type::__call()`
 * Remove `Type::getBaseType()`, use `WrappingTypeInterface::getWrappedType()` instead
 * Remove `Type::asNonNullable()`, use `NullableType::getWrappedType()` instead
 * Remove `CompositeTypeTrait`

Webhook
-------

 * [BC BREAK] `RequestParserInterface::parse()` return type changed from `RemoteEvent|null` to `RemoteEvent|array<RemoteEvent>|null`.
   Projects relying on the `WebhookController` of the component are not affected by the BC break. Classes already implementing
   this interface are unaffected. Custom callers of this method will need to be updated to handle the extra array return type.

Yaml
----

 * Deprecate parsing duplicate mapping keys whose value is `null`
