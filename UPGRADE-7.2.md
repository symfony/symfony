UPGRADE FROM 7.1 to 7.2
=======================

Symfony 7.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/7.2/setup/upgrade_minor.html).

If you're upgrading from a version below 7.1, follow the [7.1 upgrade guide](UPGRADE-7.1.md) first.

Cache
-----

 * `igbinary_serialize()` is not used by default when the igbinary extension is installed
 * Deprecate making `cache.app` adapter taggable, use the `cache.app.taggable` adapter instead

Console
-------

 * [BC BREAK] Add ``--silent`` global option to enable the silent verbosity mode (suppressing all output, including errors)
   If a custom command defines the `silent` option, it must be renamed before upgrading.
 * Add `isSilent()` method to `OutputInterface`

DependencyInjection
-------------------

 * Deprecate `!tagged` tag, use `!tagged_iterator` instead

Form
----

 * Deprecate the `VersionAwareTest` trait, use feature detection instead

FrameworkBundle
---------------

 * [BC BREAK] The `secrets:decrypt-to-local` command terminates with a non-zero exit code when a secret could not be read
 * Deprecate `session.sid_length` and `session.sid_bits_per_character` config options

HttpFoundation
--------------

 * Deprecate passing `referer_check`, `use_only_cookies`, `use_trans_sid`, `trans_sid_hosts`, `trans_sid_tags`, `sid_bits_per_character` and `sid_length` options to `NativeSessionStorage`

Ldap
----

 * Add methods for `saslBind()` and `whoami()` to `ConnectionInterface` and `LdapInterface`

Mailer
------

* Deprecate `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

  The `testIncompleteDsnException()` test is no longer provided by default. If you make use of it by implementing the `incompleteDsnProvider()` data providers,
  you now need to use the `IncompleteDsnTestTrait`.

Messenger
---------

 * Add `getRetryDelay()` method to `RecoverableExceptionInterface`

Notifier
--------

 * Deprecate `TransportFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

   The `testIncompleteDsnException()` and `testMissingRequiredOptionException()` tests are no longer provided by default. If you make use of them (i.e. by implementing the
   `incompleteDsnProvider()` or `missingRequiredOptionProvider()` data providers), you now need to use the `IncompleteDsnTestTrait` or `MissingRequiredOptionTestTrait` respectively.

Security
--------

 * Add `$token` argument to `UserCheckerInterface::checkPostAuth()`
 * Deprecate argument `$secret` of `RememberMeToken` and `RememberMeAuthenticator`
 * Deprecate passing an empty string as `$userIdentifier` argument to `UserBadge` constructor
 * Deprecate returning an empty string in `UserInterface::getUserIdentifier()`

SecurityBundle
--------------

 * Deprecate XML-configured custom authenticators and providers under security namespace; they must now have their own:

   ```diff
   <srv:container xmlns="http://symfony.com/schema/dic/security"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:srv="http://symfony.com/schema/dic/services"
   +           xmlns:custom="http://example.com/schema"
               xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/security
   -    https://symfony.com/schema/dic/security/security-1.0.xsd">
   +    https://symfony.com/schema/dic/security/security-1.0.xsd
   +    http://example.com/schema http://example.com/schema.xsd">
   +    <!-- the line above can be omitted if the schema does not have a definition -->

        <config>
           <provider name="custom_provider">
   -           <provider-name>
   -               <config key="value"/>
   -           </provider-name>
   +           <custom:provider-name>
   +               <custom:config key="value"/>
   +           </custom:provider-name>
           </provider>
        </config>
   </srv:container>
   ```

Serializer
----------

 * Deprecate the `csv_escape_char` context option of `CsvEncoder` and the `CsvEncoder::ESCAPE_CHAR_KEY` constant
 * Deprecate `CsvEncoderContextBuilder::withEscapeChar()` method
 * Deprecate `AdvancedNameConverterInterface`, use `NameConverterInterface` instead

String
------

 * `truncate` method now also accept `TruncateMode` enum instead of a boolean:
   * `TruncateMode::Char` is equivalent to `true` value ;
   * `TruncateMode::WordAfter` is equivalent to `false` value ;
   * `TruncateMode::WordBefore` is a new mode that will cut the sentence on the last word before the limit is reached.

Translation
-----------

 * Deprecate `ProviderFactoryTestCase`, extend `AbstractTransportFactoryTestCase` instead

   The `testIncompleteDsnException()` test is no longer provided by default. If you make use of it by implementing the `incompleteDsnProvider()` data providers,
   you now need to use the `IncompleteDsnTestTrait`.

 * Deprecate passing an escape character to `CsvFileLoader::setCsvControl()`

TwigBridge
----------

 * Deprecate passing a tag to the constructor of `FormThemeNode`

Webhook
-------

 * [BC BREAK] `RequestParserInterface::parse()` return type changed from
   `?RemoteEvent` to `RemoteEvent|array<RemoteEvent>|null`. Classes already
   implementing this interface are unaffected but consumers of this method
   will need to be updated to handle the new return type. Projects relying on
   the `WebhookController` of the component are not affected by the BC break

Yaml
----

 * Deprecate parsing duplicate mapping keys whose value is `null`
