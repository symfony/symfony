UPGRADE FROM 8.1 to 8.2
=======================

Symfony 8.2 is a minor release. According to the Symfony release process, there should be no significant
backward compatibility breaks. Minor backward compatibility breaks are prefixed in this document with
`[BC BREAK]`, make sure your code is compatible with these entries before upgrading.
Read more about this in the [Symfony documentation](https://symfony.com/doc/8.2/setup/upgrade_minor.html).

If you're upgrading from a version below 8.1, follow the [8.1 upgrade guide](UPGRADE-8.1.md) first.

AssetMapper
-----------

 * Add argument `$useEsm` to `ImportMapConfigReader::createRemoteEntry()`

Crowdin Translation Provider
----------------------------

 * Add `$projectId` constructor parameter to `CrowdinProvider`

DoctrineBridge
--------------

 * `UniqueEntity` now throws a `ConstraintDefinitionException` when a checked field holds an array or is a to-many
   association and the default `findBy` repository method is used. Such fields were silently validated against a
   query that could not match. Use the `repositoryMethod` option to provide a method that can query them
 * Deprecate `DoctrineCloseConnectionMiddleware` in favor of the new `DoctrineDbalCloseConnectionMiddleware`,
   which targets DBAL connections instead of entity managers. Its first argument is a `ConnectionRegistry` instead
   of a `ManagerRegistry`, and its second one takes connection names, either one or a list, instead of an entity
   manager name. Passing no name now closes every DBAL connection, where the deprecated middleware closed the
   connection of the default entity manager

Form
----

 * [BC BREAK] Children that use the `form_attr` option now carry the id the themes render on the `<form>`
   element instead of the id of the element wrapping the fields, so that the reference resolves. That id is
   the `attr.id` of the root form when the application set one, the string given to `form_attr` when the
   option is a string, wherever it sits in the form tree, and `form_<root id>` otherwise. Forms that do not
   use `form_attr` are unaffected: the `form_id` view variable stays `null` when nothing references it
 * Deprecate the `regions` option of `TimezoneType`, it has had no effect since 5.0 and will be removed in 9.0
 * `TimezoneType` with the `intl` option enabled now offers the identifier PHP reports as canonical when ICU
   keys a zone and its legacy aliases under one display name, so `Asia/Kolkata` is offered where `Asia/Calcutta`
   used to be. The aliases stay submittable and are resolved to the offered identifier, so a stored value keeps
   designating the same choice, but reading it back returns the offered identifier
 * `TimezoneType` now resolves `UTC` and `Etc/UTC` to each other, the `intl` option deciding which one is
   offered, where submitting the one the option does not offer used to be rejected

FrameworkBundle
---------------

 * Deprecate the `framework.ide` config option, use the `SYMFONY_IDE` env var instead
 * BrowserKit assertions are no longer verbose by default. Failed response assertions no longer include the response body unless `setBrowserKitAssertionsAsVerbose(true)` is called or `verbose: true` is passed to the assertion.

HttpClient
----------

 * [BC BREAK] Widen the type of the `$buffer` argument of `HttpOptions::buffer()` from `bool` to `mixed`, so that the stream and closure forms the option accepts can be passed; a class extending `HttpOptions` and overriding that method must widen it too

HttpFoundation
--------------

 * Add argument `$version` to `UriSigner::sign()`, `UriSigner::check()`, `UriSigner::checkRequest()`, and `UriSigner::verify()`
 * Deprecate the `Request::$trustedHosts` property, it is never populated anymore since trusted hosts are
   matched against a single combined regexp, and will be removed in 9.0. Populating it makes `getHost()`
   trigger a deprecation; reading it is not reported, since PHP provides no way to intercept access to a
   static property

Lock
----

 * Add argument `$advisory` to `StoreFactory::createStore()`

Loco Translation Provider
-------------------------

 * Deprecate passing `LocoProvider` and `LocoProviderFactory` constructor a `$defaultLocale` argument. It has no effect and can be removed.
 * Deprecate passing no domains or `*` to `LocoProvider::read()`, configure your loco provider domains as an associative array with an empty string key and `*` as value

Messenger
---------

 * `RedispatchMessage` now dispatches to the senders configured for the message (via
   `framework.messenger.routing` or `#[AsMessage]`) when `$transportNames` is empty, instead of sending to no
   sender at all. Code that relied on `new RedispatchMessage($message)`, or on an empty array or string, to
   force in-process handling of a message that also has a configured route must now carry an empty
   `TransportNamesStamp` on the inner envelope:

   ```php
   new RedispatchMessage(new Envelope($message, [new TransportNamesStamp([])]))
   ```

   Note that a message sent to a transport is no longer handled in process, so `RedispatchMessageHandler`
   returns `null` for it instead of the result of the handler

Security
--------

 * [BC BREAK] A failing `#[IsCsrfTokenValid]` attribute now throws
   `Symfony\Component\Security\Http\Exception\InvalidCsrfTokenException`, which extends `HttpException` and
   carries a 403 status, instead of `Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException`, which
   extends `AuthenticationException`. The firewall no longer turns the failure into a login redirect or a 401, and
   code catching the `Security\Core` exception for this case must catch the `Security\Http` one instead
 * Add argument `$targetUri` to `ImpersonateUrlGenerator::generateImpersonationPath()` and `ImpersonateUrlGenerator::generateImpersonationUrl()`

SecurityBundle
--------------

 * Deprecate the `remember_me` option of the `form_login`, `json_login`, `login_link`, and `access_token` authenticators, as it has no effect
 * Deprecate configuring an access control rule with many `roles`, use `allow_if` or role hierarchy instead
 * Deprecate configuring both an access control rule `allow_if` and `roles`, update `allow_if` instead
 * A service used as a firewall `success_handler` or `failure_handler` is now wired as-is, so decorating it
   takes effect where it used to be silently ignored. Such a decorator must forward `setOptions()`, and
   `setFirewallName()` for success handlers, to the service it decorates whenever that service relies on them,
   as `DefaultAuthenticationSuccessHandler` and `DefaultAuthenticationFailureHandler` do. Without forwarding,
   the authenticator options and the session target path are lost, and a successful login redirects to `/`

Serializer
----------

 * Deprecate denormalizing an array that is not a list into a `list`-typed property, in version 9.0 a `Symfony\Component\Serializer\Exception\NotNormalizableValueException` will be thrown when the input does not satisfy `array_is_list()`
 * Denormalize the elements of a union-typed collection, e.g. `array<Foo|Bar>`, instead of returning the raw data. An element that matches no member of the union, or a key whose type does not match, now throws instead of being returned as-is

Translation
-----------

 * `FilteringProvider::read()` now returns an empty `TranslatorBag` when none of the requested locales match the configured ones, and a bag of empty catalogues when no requested domain matches, instead of delegating to the wrapped provider

Tui
---

 * [BC BREAK] Add argument `$multiselect` as the third argument of `SelectListWidget::__construct()`, moving `$keybindings` to fourth position

TwigBridge
----------

 * `form_start()` renders an `id` attribute on the `<form>` element when a child uses the `form_attr` option,
   taken from the new `form_id` view variable. Forms that do not use it render as before. Set `attr.id` on
   the root form to choose the id, or `attr: {id: false}` to render none. A custom theme overriding the
   `form_start` block renders no id until that block is updated

Validator
---------

 * Add argument `$restrictGroups` to `Valid::__construct()`
 * [BC BREAK] Remove the `GroupSequence::$cascadedGroup` property, it has had no effect since the validator stopped reading it in 2014, and reading it has thrown since 7.4 typed it without a default
 * Add argument `$cascadeCurrentGroup` to `GroupSequenceProvider::__construct()`
 * The `File` constraint no longer narrows the configured `mimeTypes` option with mime types auto-derived from the matched extension when `extensions` is also configured.
   The two options are now checked independently: `extensions` validates the file extension, and `mimeTypes` validates the detected mime type.

   In previous versions, the effective mime-type list was narrowed to the mime types auto-derived from the matching extension. For example, a CSV file detected as `text/plain` could be rejected by this constraint because the `mimeTypes` list was narrowed to the mime types derived from `csv`:

   ```php
   #[Assert\File(
       extensions: ['csv'],
       mimeTypes: ['text/csv', 'text/plain'],
   )]
   ```

   In Symfony 8.2, the configured `mimeTypes` list is used as-is, while the `csv` extension is still enforced separately.
