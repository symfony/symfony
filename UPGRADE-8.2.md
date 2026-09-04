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
 * Deprecate `DoctrineCloseConnectionMiddleware` in favor of `DoctrineDbalCloseConnectionMiddleware`,
   `DoctrineOpenTransactionLoggerMiddleware` in favor of `DoctrineDbalOpenTransactionLoggerMiddleware`,
   and `DoctrinePingConnectionMiddleware` in favor of `DoctrineDbalPingConnectionMiddleware`.
   Those new middlewares target DBAL connections instead of entity managers. They are instantiated with a
   `ConnectionRegistry` instead of a `ManagerRegistry`, and connection names (either one or a list) instead
   of an entity manager name. Passing no name now targets every DBAL connection, where the deprecated close and
   logger middlewares targeted the connection of the default entity manager, and the deprecated ping middleware
   targeted the connections of every entity manager. Beware that `DoctrineDbalOpenTransactionLoggerMiddleware`
   takes its logger as second argument and its connection names as third, where
   `DoctrineOpenTransactionLoggerMiddleware` took the entity manager name as second argument and its logger as third.
   Also note that `DoctrineDbalPingConnectionMiddleware` does not reset closed entity managers as its deprecated
   counterpart did: workers already reset them between messages

Filesystem
----------

 * Deprecate passing an empty string as the base path to `Path::isBasePath()`, pass `"/"` instead.
   Both answer identically today, an empty base path being treated as the root

Form
----

 * Add `createStepGroup()` method to `FormFlowBuilderInterface`; implementations not extending the default `FormFlowBuilder` must implement it
 * Add `setGroup()`, `addStep()` and `removeStep()` methods to `StepFlowBuilderConfigInterface`; implementations not extending the default `StepFlowBuilder` must implement them
 * Add `isGroup()`, `getSteps()`, `hasStep()` and `getStep()` methods to `StepFlowConfigInterface`; implementations not extending the default `StepFlowBuilder` must implement them
 * [BC BREAK] Children that use the `form_attr` option now carry the id the themes render on the `<form>`
   element instead of the id of the element wrapping the fields, so that the reference resolves. That id is
   the `attr.id` of the root form when the application set one, the string given to `form_attr` when the
   option is a string, wherever it sits in the form tree, and `form_<root id>` otherwise. Forms that do not
   use `form_attr` are unaffected: the `form_id` view variable stays `null` when nothing references it
 * Deprecate the `regions` option of `TimezoneType`, it has had no effect since 5.0 and will be removed in 9.0
 * Deprecate the `FormTypePasswordHasherExtension` class and the `registerPassword()` and `hashPasswords()`
   methods of `PasswordHasherListener`: the password of a field that uses the `hash_property_path` option
   is now hashed during the `form.post_validate` event, which is dispatched only when the validator
   extension is enabled. Wire `FormTypePasswordHasherExtension` yourself to keep hashing passwords in a
   form system that does not use the validator extension
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
 * Deprecate the `framework.fragments.hinclude_default_template` config option and the `fragment.renderer.hinclude.global_template` parameter; use the `esi` or `inline` fragment renderer, or [Symfony UX Turbo](https://ux.symfony.com/turbo), instead

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

HttpKernel
----------

 * Deprecate the `HIncludeFragmentRenderer` class, use the `EsiFragmentRenderer` or `InlineFragmentRenderer`, or [Symfony UX Turbo](https://ux.symfony.com/turbo), instead

Lock
----

 * Add argument `$advisory` to `StoreFactory::createStore()`

Loco Translation Provider
-------------------------

 * Deprecate passing `LocoProvider` and `LocoProviderFactory` constructor a `$defaultLocale` argument. It has no effect and can be removed.
 * Deprecate passing no domains or `*` to `LocoProvider::read()`, configure your loco provider domains as an associative array with an empty string key and `*` as value

Mailer
------

 * [AhaSend] Deprecate sending through the legacy v1 API, use a v2 API key and add your account id to the DSN
 * [Brevo] Deprecate the "templateid" and "params" email headers, use a `RemoteTemplateEmail` instead
 * [Mailgun] Deprecate the "template" email header, use a `RemoteTemplateEmail` instead
 * [Mailjet] Deprecate the "X-MJ-TemplateID" email header, use a `RemoteTemplateEmail` instead
 * Deprecate sending an S/MIME message unencrypted when a recipient has no certificate (the default
   `SmimeEncryptedMessageListener::ON_MISSING_CERTIFICATE_SEND_UNENCRYPTED` behavior); it will throw in 9.0.
   Set the `on_missing_certificate` option (or the `X-SMime-Encrypt` header) to `fail`, `encrypt` or `skip`:

   ```yaml
   framework:
       mailer:
           smime_encrypter:
               on_missing_certificate: 'fail'
   ```

 * `DkimSignedMessageListener` now listens with priority `-228` instead of `-128`, so that DKIM signs the
   S/MIME encrypted message instead of racing `SmimeEncryptedMessageListener` for the same priority. Use the
   new `DkimSignedMessageListener::PRIORITY`, `SmimeSignedMessageListener::PRIORITY` and
   `SmimeEncryptedMessageListener::PRIORITY` constants if you register listeners that must run around them.

 * Deprecate reading a value that is not a boolean with `Dsn::getBooleanOption()`; it will throw in 9.0. The
   boolean values it accepts are `1`/`0`, `true`/`false`, `on`/`off`, `yes`/`no` and the empty string, which reads as `false`

Messenger
---------

 * The Amazon SQS transport no longer deduplicates the messages sent to a FIFO queue on their content: the
   `MessageDeduplicationId` sent by default is now unique per message, so dispatching the same message twice
   within five minutes delivers it twice. To keep deduplicating, set the id explicitly with `AmazonSqsFifoStamp`
   or with a message implementing `MessageDeduplicationAwareInterface` (together with `AddFifoStampMiddleware`);
   the `ContentBasedDeduplication` attribute of the queue alone is not enough, as an explicit id overrides it.
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

Notifier
--------

 * Deprecate `NovuSubscriberRecipient::getOverrides()` and its `$overrides` constructor parameter, pass overrides to `NovuOptions` instead
 * Deprecate declaring `getAdminRecipients()` on a `NotifierInterface` implementation without implementing `AdminRecipientsProviderInterface`
 * Deprecate reading a value that is not a boolean with `Dsn::getBooleanOption()`; it will throw in 9.0. The
   boolean values it accepts are `1`/`0`, `true`/`false`, `on`/`off`, `yes`/`no` and the empty string, which reads as `false`

RateLimiter
-----------

 * `CompoundLimiter::consume()` now stops consuming at the first limiter that rejects the request;
   list limiters from the most specific to the most global to spare shared quotas from rejected hits

Scheduler
---------

 * Deprecate `Schedule::with()`. It returns a schedule that keeps only the event dispatcher, so a lock or a
   state set on the original schedule is silently dropped, and the resulting schedule then runs unlocked.

   To derive a schedule from another one, clone it. The clone shares the dispatcher, the lock and the state,
   and its list of messages is independent, so adding to one does not affect the other:

   ```php
   $new = clone $schedule;
   $new->add($message);
   ```

   To build an unrelated schedule, which is what `with()` actually did, construct one:

   ```php
   // before
   $new = $schedule->with($message);

   // after
   $new = (new Schedule($dispatcher))->add($message);
   ```

Security
--------

 * [BC BREAK] A failing `#[IsCsrfTokenValid]` attribute now throws
   `Symfony\Component\Security\Http\Exception\InvalidCsrfTokenException`, which extends `HttpException` and
   carries a 403 status, instead of `Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException`, which
   extends `AuthenticationException`. The firewall no longer turns the failure into a login redirect or a 401, and
   code catching the `Security\Core` exception for this case must catch the `Security\Http` one instead
 * Add argument `$targetUri` to `ImpersonateUrlGenerator::generateImpersonationPath()` and `ImpersonateUrlGenerator::generateImpersonationUrl()`
 * Deprecate passing more than one Security attribute to `AccessDecisionManager::decide()`, pass a single attribute instead.
   The `$allowMultipleAttributes` argument will be removed in 9.0
 * Add argument `$parameters` to `LoginLinkHandlerInterface::createLoginLink()`
 * Add argument `$parameters` to `SignatureHasher::computeSignatureHash()`, `SignatureHasher::acceptSignatureHash()` and `SignatureHasher::verifySignatureHash()`

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

String
------

 * Add argument `$regexp` to `AbstractString::lower()`, `AbstractString::upper()`, `AbstractString::title()`,
   `AbstractUnicodeString::localeLower()`, `AbstractUnicodeString::localeUpper()` and `AbstractUnicodeString::localeTitle()`

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
 * Deprecate the `render_hinclude()` Twig function; use `render_esi()` or `render()`, or [Symfony UX Turbo](https://ux.symfony.com/turbo), instead

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
