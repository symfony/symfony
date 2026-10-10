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

Config
------

 * [BC BREAK] The `enabled` node of the sections created with `canBeEnabled()` or `canBeDisabled()` is declared
   with `resolvesAtCompileTime()`: its env vars are read while the container is compiled, where the extension
   used to get a placeholder that was always truthy. A section configured with `enabled: '%env(bool:FOO)%'` is
   now really disabled when `FOO` is false, and compiling fails when `FOO` has no value at that point

Console
-------

 * [BC BREAK] A token that names a registered sub-command runs it instead of binding to an argument of its
   parent: with both `app:import` and `app:import:users` registered, `app:import users` and `app import users`
   now run `app:import:users`, where `users` was bound to the `file` argument of `app:import`. Write
   `app:import -- users` to bind it as an argument
 * [BC BREAK] Positional `ArrayInput` parameters bind to the argument slots in order, where they were validated
   by index and never read: `new ArrayInput(['foo'])` binds `foo` to the first argument of the definition
 * A bare namespace does not dispatch `ConsoleEvents::ERROR` anymore when it lists its commands: nothing fails,
   the listing is still written on the error output and the exit code is still `1`
 * The application listing collapses the commands below a registered command to that command's own line;
   `list <namespace>`, the `--raw` option and the `json` and `md` formats keep listing every command

DependencyInjection
-------------------

 * [BC BREAK] A top-level extension key that holds a value which is not an array is now passed to that
   extension instead of being replaced by an empty array. `lock: 'redis://example.com'` configures the lock,
   where it used to be ignored. An extension whose configuration tree does not accept the value now reports
   an `InvalidTypeException` instead of ignoring it
 * Bundles that declare no constructor and inherit `boot()`, `shutdown()` and `setContainer()` from
   `AbstractBundle` are now instantiated on demand instead of on every boot. The `$bundles` property of
   the kernel holds only the bundles that have been instantiated, call `getBundles()` to get them all
 * Deprecate the `Symfony\Component\EventDispatcher\EventDispatcherInterface` autowiring alias, type
   `Symfony\Contracts\EventDispatcher\EventDispatcherInterface` instead. Autowiring hands out the event
   dispatcher of the application, which must not be mutated at runtime, so the type to ask for is the one
   that only dispatches. A service that needs to read the listeners of the dispatcher, a debug tool for
   instance, can still be given the `event_dispatcher` service explicitly
 * `AsDecorator::$priority` and `AsTagDecorator::$priority` are now `?int` and default to `null`, which means "no priority declared".
   Code that read the properties as an `int` should read `$attribute->priority ?? 0`

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

ErrorHandler
------------

 * `SerializerErrorRenderer` serves the problem documents it renders as JSON with the `application/problem+json` content type, as defined by RFC 9457, instead of `application/json`.
   Only the bodies that hold the `type`, `title` and `status` members, as produced by `ProblemNormalizer`, are affected.
   A client or a test that compares the header to `application/json`, or that calls `assertResponseFormatSame('json')` on such a response, should accept `application/problem+json`, which `Request::getFormat()` maps to the `problem` format.

EventDispatcher
---------------

 * The `event_dispatcher` service is a `CompiledEventDispatcher` instead of an `EventDispatcher`; both implement `EventDispatcherInterface`, which is the type to use
 * `CompileListenersPass` compiles away the `addListener()` calls of the dispatcher definitions at `PassConfig::TYPE_AFTER_REMOVING`: a compiler pass reading them must run before, and a decorator of the dispatcher no longer receives them at runtime
 * Deprecate calling `addListener()`, `addSubscriber()`, `removeListener()` and `removeSubscriber()` on a
   `CompiledEventDispatcher`, which is what the `event_dispatcher` service is. Declare the listener in the
   container, or add it to a `ScopedEventDispatcher` wrapping the shared one and dispatch through that:

   ```php
   $dispatcher = new ScopedEventDispatcher($container->get('event_dispatcher'));
   $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(10));

   (new Worker($receivers, $bus, $dispatcher))->run();
   ```

   A test that adds a listener to the `event_dispatcher` service is the most likely place to meet this deprecation; register a listener service in the test container instead
 * `TraceableEventDispatcher` calls the listeners itself instead of calling `dispatch()` on the dispatcher it decorates, so the dispatching logic of a custom dispatcher is bypassed in debug mode
 * `AsEventListener::$priority` and the `$priority` of the Workflow `As*Listener` attributes are now `?int` and default to `null`, which means "no priority declared"; the `kernel.event_listener` tags they produce carry `null` too, and code that read the property as an `int` should read `$attribute->priority ?? 0`

ExpressionLanguage
------------------

 * Deprecate the built-in `constant()` and `enum()` functions, register a `ConstantFunctionProvider` listing
   the constants that expressions may read instead. The deprecation is triggered when an expression using them
   is parsed. Each entry is a constant name in which `*` matches any sequence of characters except `::`:

   ```php
   $expressionLanguage = new ExpressionLanguage(null, [new ConstantFunctionProvider(['App\Security\Roles::ROLE_*', 'App\Enum\*::*'])]);
   ```

   Pass `['*', '*::*']` to keep allowing every constant. The expression languages created by Symfony itself
   do so already

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
 * Deprecate passing the `$debug` argument to `DependencyInjection\Configuration::__construct()`, the config tree no longer depends on `kernel.debug`
 * BrowserKit assertions are no longer verbose by default. Failed response assertions no longer include the response body unless `setBrowserKitAssertionsAsVerbose(true)` is called or `verbose: true` is passed to the assertion.
 * Deprecate the `framework.fragments.hinclude_default_template` config option and the `fragment.renderer.hinclude.global_template` parameter; use the `esi` or `inline` fragment renderer, or [Symfony UX Turbo](https://ux.symfony.com/turbo), instead
 * Most of what this bundle configured now lives in a bundle shipped by the component itself: `asset`,
   `asset_mapper`, `cache`, `html_sanitizer`, `http_client`, `json_streamer`, `lock`, `mailer`, `messenger`,
   `notifier`, `property_access`, `property_info`, `rate_limiter`, `remote_event`, `router`, `scheduler`,
   `semaphore`, `serializer`, `translation`, `type_info`, `uid`, `validation`, `web_link`, `webhook` and
   `workflow`. Each of them is registered automatically when its component is installed, and its services are
   then registered without any configuration; set `<key>.enabled` to `false` to disable them. The matching
   `framework.*` key keeps working, as an alias of the bundle's own configuration. Three are spelled
   differently at the root: `framework.assets` is `asset`, `framework.translator` is `translation` and
   `framework.workflows` is `workflow`
 * Because the forwarded keys are removed from the framework configuration, `debug:config framework <key>` no
   longer resolves; use `debug:config <key>` instead
 * `CacheBundle` and `RouterBundle` are registered whether or not their configuration is set, `symfony/cache`
   and `symfony/routing` being hard requirements of this bundle; `cache` has no `enabled` flag, and the router
   stays off until it is configured, as before
 * Every traceable decorator and data collector the moved sections own is registered in debug mode and dropped
   when the profiler is disabled, where some of them used to follow the profiler alone
 * The component bundles drop at compile time the services whose dependencies are missing, for instance their cache pools when no `cache.system` pool is registered
 * `ValidationBundle` no longer enables the validator when forms are enabled
 * `Console\Application` does not instantiate every bundle anymore, only the ones that override the deprecated
   `Bundle::registerCommands()` method, listed in the new `console.command.bundles` container parameter; that
   parameter exists only to support the deprecated method and goes away with it in 9.0
 * Deprecate `CacheWarmer\AbstractPhpFileCacheWarmer`, `CacheWarmer\CachePoolClearerCacheWarmer`, `Command\CachePoolClearCommand`, `Command\CachePoolDeleteCommand`, `Command\CachePoolInvalidateTagsCommand`, `Command\CachePoolListCommand` and `Command\CachePoolPruneCommand`, use their counterparts from the Cache component instead
 * Deprecate `CacheWarmer\RouterCacheWarmer`, `Command\RouterMatchCommand`, `Controller\RedirectController`, `Routing\AttributeRouteControllerLoader`, `Routing\DelegatingLoader`, `Routing\RedirectableCompiledUrlMatcher`, `Routing\Router` and `Routing\Attribute\AsRoutingConditionService`, use their counterparts from the Routing component instead. The `Symfony\Bundle\FrameworkBundle\Controller\RedirectController` service id, which route definitions reference, keeps working as a deprecated alias, and `AsRoutingConditionService` no longer tags the subclasses of the classes that carry it
 * Deprecate `Routing\RouteLoaderInterface`, use the `#[AsRouteLoader]` attribute from the Routing component instead. Unlike the interface, the attribute is not inherited: a class extending an annotated one has to carry it too
 * Deprecate `CacheWarmer\TranslationsCacheWarmer`, `Command\TranslationDebugCommand`, `Command\TranslationExtractCommand`, `DependencyInjection\Compiler\TranslationLintCommandPass`, `DependencyInjection\Compiler\TranslationUpdateCommandPass` and `Translation\Translator`, use their counterparts from the Translation component instead
 * Deprecate `CacheWarmer\SerializerCacheWarmer`, use the one from the Serializer component instead
 * Deprecate `CacheWarmer\ValidatorCacheWarmer`, use the one from the Validator component instead
 * Deprecate `DependencyInjection\Compiler\AssetsContextPass`, use the one from the Asset component instead
 * Deprecate `DependencyInjection\Compiler\JsonPathPass`, use the one from the JsonPath component instead
 * Deprecate `Controller\TemplateController`, use the one from TwigBundle instead. Its service now exists when TwigBundle is registered instead of whenever routing is, and the old service id keeps working as a deprecated alias
 * The secrets vault no longer loads env vars when its directory is in the project but does not exist when the container is built (`config/secrets/` by default). The env var of `framework.secret` is then not derived from `SYMFONY_DECRYPTION_SECRET` anymore when it is empty or not defined: define it, or create the vault. In non-debug environments, clear the cache after creating the first vault

HttpClient
----------

 * [BC BREAK] Widen the type of the `$buffer` argument of `HttpOptions::buffer()` from `bool` to `mixed`, so that the stream and closure forms the option accepts can be passed; a class extending `HttpOptions` and overriding that method must widen it too
 * [BC BREAK] An explicit `http_version` of `2.0` makes `CurlHttpClient` and `AmpHttpClient` require HTTP/2: they use prior knowledge on `http://` URLs instead of offering an upgrade, and offer only `h2` during the TLS handshake on `https://` URLs (with curl 8.10 or higher); servers that speak only HTTP/1.1 are not reachable that way anymore, unset the option for them

HttpFoundation
--------------

 * Add argument `$version` to `UriSigner::sign()`, `UriSigner::check()`, `UriSigner::checkRequest()`, and `UriSigner::verify()`
 * Deprecate not passing an expiration to `UriSigner::sign()` when the signer has no default one; pass it, or set a default with the `$defaultExpiration` argument of `UriSigner::__construct()` or the `framework.uri_signer.expiration` option
 * Deprecate the `Request::$trustedHosts` property, it is never populated anymore since trusted hosts are
   matched against a single combined regexp, and will be removed in 9.0. Populating it makes `getHost()`
   trigger a deprecation; reading it is not reported, since PHP provides no way to intercept access to a
   static property
 * Deprecate saving changes made to the value of a session attribute without calling `set()` afterwards.
   In 9.0, objects read from the session will be copies and only the values passed to `set()` will be saved.
   These changes are reported when the `$debug` argument of `AttributeBag::__construct()` is `true`, which
   FrameworkBundle does when `kernel.debug` is enabled. Set the `framework.session.isolate_attributes` option
   to `true`, or pass `true` as the `$isolate` argument of `AttributeBag::__construct()`, to opt in to the new
   behavior.

   *Before*
   ```php
   $session->get('cart')->add($item);
   ```

   *After*
   ```php
   $cart = $session->get('cart');
   $cart->add($item);
   $session->set('cart', $cart);
   ```
 * `RedirectResponse` no longer adds a default `Cache-Control: no-cache, private` header to 308 responses, as for 301 responses, so browsers can cache them. Pass `['Cache-Control' => 'no-cache, private']` as the `$headers` argument to keep the previous behavior.

HttpKernel
----------

 * [BC BREAK] When a route matches the request path but declares a `scheme` the request does not use,
   `RouterListener` now returns the redirect response itself at priority 32, instead of setting
   `_route` and `_controller` and letting the request continue to `RedirectController`. The response
   is unchanged, but listeners registered below priority 32, the firewall included, no longer run on
   such a request. Previously a firewall `check_path`, `logout_path` or `switch_user` target declared
   with `schemes: ['https']` was still acted upon when requested over plain HTTP with GET or HEAD.
   Only scheme redirects are affected; a trailing-slash redirect still goes through the controller
 * Deprecate the `HIncludeFragmentRenderer` class, use the `EsiFragmentRenderer` or `InlineFragmentRenderer`, or [Symfony UX Turbo](https://ux.symfony.com/turbo), instead
 * `ErrorListener` logs exceptions whose HTTP status code is below 500 (client errors), including the ones given a
   status code by `framework.exceptions` or `#[WithHttpStatus]`, at the `warning` level instead of `error`. They no
   longer activate a `fingers_crossed` handler whose `action_level` is `error`, as in the Monolog recipe, and the logger
   that HttpKernel registers when no other is installed no longer outputs them by default. Lower the `action_level` to
   `warning`, set the `log_level` of `framework.exceptions` or use the `#[WithLogLevel]` attribute on the exception
   class to keep the previous behavior
 * `Kernel::boot()` now iterates over the `$bundles` property instead of calling `getBundles()`, so that the
   bundles that have nothing to do at boot time are not instantiated
 * `#[RateLimit]` now consumes its tokens on `kernel.controller`, before the controller arguments are resolved, unless its key is a Closure or an Expression that uses `args`.
   Such a limit now runs before the attributes handled on `kernel.controller_arguments`, like `#[IsGranted]`, whatever their order, so the requests they deny consume tokens too
 * `#[Cache]` now applies to 308 responses, as to 301 responses, so a controller returning a 308 gets the cache headers declared by the attribute.

JsonStreamer
------------

 * Deprecate the `JsonStreamWriter` and `JsonStreamReader` autowiring aliases, type `StreamWriterInterface` and `StreamReaderInterface` instead

Lock
----

 * Add argument `$advisory` to `StoreFactory::createStore()`

Loco Translation Provider
-------------------------

 * Deprecate the `$defaultLocale` argument of `LocoProvider` and `LocoProviderFactory`, it has no effect and can be removed
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
 * Deprecate the autowiring aliases named after the webhook request parsers of the bridges, type `Symfony\Component\Webhook\Client\RequestParserInterface` and select the parser with `#[Target]` instead:

   ```php
   public function __construct(
       #[Target('mailer.mailgun')] private RequestParserInterface $parser,
   ) {
   }
   ```

Messenger
---------

 * The Amazon SQS transport no longer deduplicates the messages sent to a FIFO queue on their content: the
   `MessageDeduplicationId` sent by default is now unique per message, so dispatching the same message twice
   within five minutes delivers it twice. To keep deduplicating, set the id explicitly with `AmazonSqsFifoStamp`
   or with a message implementing `MessageDeduplicationAwareInterface` (together with `AddFifoStampMiddleware`);
   the `ContentBasedDeduplication` attribute of the queue alone is not enough, as an explicit id overrides it.
 * `RedispatchMessage` now dispatches to the senders configured for the message (via
   `messenger.routing` or `#[AsMessage]`) when `$transportNames` is empty, instead of sending to no
   sender at all. Code that relied on `new RedispatchMessage($message)`, or on an empty array or string, to
   force in-process handling of a message that also has a configured route must now carry an empty
   `TransportNamesStamp` on the inner envelope:

   ```php
   new RedispatchMessage(new Envelope($message, [new TransportNamesStamp([])]))
   ```

   Note that a message sent to a transport is no longer handled in process, so `RedispatchMessageHandler`
   returns `null` for it instead of the result of the handler
 * Deprecate `RedispatchMessageHandler`. Schedules can keep using `RedispatchMessage`, as the scheduler transport
   now yields the message it wraps with a `RedispatchStamp`. Elsewhere, dispatch that message with a
   `TransportNamesStamp` instead:

   ```php
   // before
   $bus->dispatch(new RedispatchMessage($message, 'async'));

   // after
   $bus->dispatch($message, [new TransportNamesStamp('async')]);
   ```
 * [BC BREAK] Messages are signed by a serializer created for each transport, instead of by decorators of the serializer services.
   Encoding a message with `messenger.default_serializer`, or with another serializer service, does not sign it anymore, and decoding with it does not check the signature.
   Send and receive the messages through their transport instead
 * Add argument `$timeout` to `MessageExecutionStrategyInterface::wait()`
 * `AsMessageHandler::$priority` is now `?int` and defaults to `null`, which means "no priority declared";
   the `messenger.message_handler` tags it produces carry `null` too. Code that read the property as an
   `int` should read `$attribute->priority ?? 0`

Mime
----

 * Add argument `$encoding` to `DataPart::fromPath()`
 * `FormDataPart` keeps the encoding of parts created with an explicit `$encoding` instead of forcing `8bit`.
   Messages normalized to JSON by the Serializer before the upgrade carry the encoding of every part, so a
   part coming from such a message is no longer forced to `8bit` in a form: consume the queues of transports
   using the Serializer before upgrading

Notifier
--------

 * Deprecate `NovuSubscriberRecipient::getOverrides()` and its `$overrides` constructor parameter, pass overrides to `NovuOptions` instead
 * Deprecate declaring `getAdminRecipients()` on a `NotifierInterface` implementation without implementing `AdminRecipientsProviderInterface`
 * Deprecate reading a value that is not a boolean with `Dsn::getBooleanOption()`; it will throw in 9.0. The
   boolean values it accepts are `1`/`0`, `true`/`false`, `on`/`off`, `yes`/`no` and the empty string, which reads as `false`
 * Deprecate the `LineNotify` transport as LINE Notify was shut down, use `LineBot` instead
 * Deprecate the Firebase `firebase://USERNAME:PASSWORD@default` DSN and the `$token` argument of `FirebaseTransport::__construct()`, use `firebase://PROJECT_ID?client_email=...&private_key_id=...&private_key=...` with the credentials of a service account instead
 * Deprecate the Firebase `AndroidNotification`, `IOSNotification` and `WebNotification` classes, use `FirebaseOptions` instead
 * Deprecate the autowiring aliases named after the webhook request parsers of the bridges, type `Symfony\Component\Webhook\Client\RequestParserInterface` and select the parser with `#[Target('notifier.twilio')]` for instance

RateLimiter
-----------

 * `CompoundLimiter::consume()` now stops consuming at the first limiter that rejects the request;
   list limiters from the most specific to the most global to spare shared quotas from rejected hits

Scheduler
---------

 * Deprecate not setting the `scheduler.use_messenger_routing` config option; it will default to `true` in 9.0
 * Deprecate `Schedule::with()`. It returns an empty schedule, so a lock or a state set on the original
   schedule is silently dropped, and the resulting schedule then runs unlocked.

   To derive a schedule from another one, clone it. The clone keeps the lock and the state, and its list of
   messages and its listeners are independent, so adding to one does not affect the other:

   ```php
   $new = clone $schedule;
   $new->add($message);
   ```

   To build an unrelated schedule, which is what `with()` actually did, construct one:

   ```php
   // before
   $new = $schedule->with($message);

   // after
   $new = (new Schedule())->add($message);
   ```
 * Deprecate passing an event dispatcher to `Schedule::__construct()`. `before()`, `after()` and `onFailure()`
   register their listeners on the schedule itself, so a listener now runs for the messages of its own schedule
   only, where it used to run for the messages of every schedule sharing that dispatcher:

   ```php
   // before
   $schedule = (new Schedule($this->dispatcher))->before($listener);

   // after
   $schedule = (new Schedule())->before($listener);
   ```
 * `PreRunEvent`, `PostRunEvent` and `FailureEvent` now carry the scheduled message itself when that message is
   redispatched, instead of the `RedispatchMessage` wrapping it
 * `MessageContext::$trigger` is a `SerializedTrigger` once its message has crossed a transport. Triggers can hold
   closures or any other non-serializable state, so only their description travels, and `getNextRunDate()` throws
   on the receiving side

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
 * Deprecate not implementing `getAuthenticationProofs()` and `setAuthenticationProofs()` in classes implementing
   `TokenInterface`; both methods will be added to the interface in 9.0, and until they are implemented no
   authentication proof is recorded on such a token, so it never satisfies `IS_AUTHENTICATED_RECENTLY`
 * Deprecate not implementing `isAuthenticatedRecently()` and `isAuthenticatedVeryRecently()` in classes implementing
   `AuthenticationTrustResolverInterface`; both methods will be added to the interface in 9.0, and `IS_AUTHENTICATED_RECENTLY`
   and `IS_AUTHENTICATED_VERY_RECENTLY` are denied by `AuthenticatedVoter` until they are implemented
 * Add argument `$parameters` to `LoginLinkHandlerInterface::createLoginLink()`
 * Add argument `$parameters` to `SignatureHasher::computeSignatureHash()`, `SignatureHasher::acceptSignatureHash()` and `SignatureHasher::verifySignatureHash()`
 * Deprecate not passing the `$enforceAtJwtType` argument to `OidcTokenHandler`; pass `true` to reject
   the tokens whose `typ` header is not `at+jwt` or `application/at+jwt`, as RFC 9068 requires from a
   JWT access token. It defaults to `false` in 8.2 and will default to `true` in 9.0
 * `AccessTokenAuthenticator` now implements `FallbackAuthenticationEntryPointInterface`, so it becomes the
   entry point of a firewall that declares no other one: an unauthenticated request now gets a 401 carrying
   the RFC 6750 `WWW-Authenticate: Bearer` challenge, where it used to get a 401 with no such header
 * [BC BREAK] The `oauth2` access token handler now refuses an introspection response reporting an `exp` in the
   past, or an `nbf` or an `iat` in the future, and one whose `exp`, `nbf` or `iat` is not a number it can read as
   a timestamp
 * Deprecate `ExceptionListener::register()`, `ExceptionListener::unregister()` and the `$dispatcher` argument
   of `Firewall::__construct()`. The firewall listens to `kernel.exception` itself and calls the exception
   listener of the firewall that matched the request, instead of adding that listener to the dispatcher on
   every request and removing it again
 * [BC BREAK] `ContextListener` does not register its `onKernelResponse()` method on the event dispatcher
   anymore. An application built on the Security component alone must register it on the `kernel.response`
   event; SecurityBundle already registers it and is not affected
 * [BC BREAK] An `#[IsGranted]` attribute whose subject reads an argument mapped with `#[MapRequestPayload]`, `#[MapQueryString]` or `#[MapUploadedFile]` now maps and validates that argument before voting.
   Voters receive the mapped value instead of the attribute, and the attributes declared before it still run before the request is mapped.
   A voter that read the attribute, for instance its `metadata` property, to vote before the request is mapped needs a subject that does not read the mapped argument instead
 * `OidcTokenHandler` logs the tokens it rejects at the `debug` level instead of `error`, including the ones signed with a key that does not match the configured one, after a key rotation for instance; errors while fetching the discovery document or the JWKS are still logged at the `error` level

SecurityBundle
--------------

 * Deprecate the `debug:security:role-hierarchy` command, use `debug:roles --format=mermaid` instead
 * The `oauth2` token handler now reads its configuration, where it used to ignore it. Giving it a string names
   the HTTP client the introspection endpoint is called with, and `oauth2: ~` no longer fails to compile
 * Deprecate the `remember_me` option of the `form_login`, `json_login`, `login_link`, and `access_token` authenticators, as it has no effect
 * Deprecate not setting the `enforce_at_jwt_type` option of the `oidc` token handler; it defaults to `false`
   in 8.2 and will default to `true` in 9.0
 * Deprecate configuring an access control rule with many `roles`, use `allow_if` or role hierarchy instead
 * Deprecate configuring both an access control rule `allow_if` and `roles`, update `allow_if` instead
 * A service used as a firewall `success_handler` or `failure_handler` is now wired as-is, so decorating it
   takes effect where it used to be silently ignored. Such a decorator must forward `setOptions()`, and
   `setFirewallName()` for success handlers, to the service it decorates whenever that service relies on them,
   as `DefaultAuthenticationSuccessHandler` and `DefaultAuthenticationFailureHandler` do. Without forwarding,
   the authenticator options and the session target path are lost, and a successful login redirects to `/`
 * Deprecate passing an event dispatcher as the 2nd argument of `FirewallListener::__construct()`, which
   `TraceableFirewallListener` inherits: the firewall does not register listeners on the dispatcher anymore,
   so the logout URL generator moves to that position

   ```php
   // before
   new FirewallListener($map, $dispatcher, $logoutUrlGenerator);

   // after
   new FirewallListener($map, $logoutUrlGenerator);
   ```

 * Deprecate the `Symfony\Component\Security\Http\Firewall` autowiring alias, the firewall listens to kernel events and is not meant to be injected
 * Deprecate the `ExpressionCacheWarmer` class, as the expressions of `access_control` rules are compiled when warming up the cache
 * The `security.expression_language` service is decorated by a `CompiledExpressionLanguage`; inject it as a `Symfony\Component\ExpressionLanguage\ExpressionLanguage`, not as a `Symfony\Component\Security\Core\Authorization\ExpressionLanguage`

Serializer
----------

 * Deprecate denormalizing an array that is not a list into a `list`-typed property, in version 9.0 a `Symfony\Component\Serializer\Exception\NotNormalizableValueException` will be thrown when the input does not satisfy `array_is_list()`
 * Denormalize the elements of a union-typed collection, e.g. `array<Foo|Bar>`, instead of returning the raw data. An element that matches no member of the union, or a key whose type does not match, now throws instead of being returned as-is
 * Deprecate denormalizing a property from its PHP name when a name converter maps it to another key (e.g. with `#[SerializedName]`), in version 9.0 such a key will be handled like any unknown key

String
------

 * Add argument `$regexp` to `AbstractString::lower()`, `AbstractString::upper()`, `AbstractString::title()`,
   `AbstractUnicodeString::localeLower()`, `AbstractUnicodeString::localeUpper()` and `AbstractUnicodeString::localeTitle()`

Translation
-----------

 * `FilteringProvider::read()` now returns an empty `TranslatorBag` when none of the requested locales match the configured ones, and a bag of empty catalogues when no requested domain matches, instead of delegating to the wrapped provider
 * `CrowdinProvider::write()` now adds the locales missing from the project before uploading, which needs an API
   token with a read and write `project.settings` scope. With a narrower token the failure is logged and those
   locales are skipped, as they were before

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

TwigBundle
----------

 * The cache warmer compiles only the form themes of `TwigBridge` that are listed in `twig.form_themes`, named in templates, or used or extended by those.
   A theme picked at runtime, through a variable in a `form_theme` tag or through `FormRenderer::setTheme()`, is compiled on first use.
   When the cache directory is read-only, name such a theme in one of your templates, e.g. in a comment, to have it warmed up

Uid
---

 * The component does not require `symfony/polyfill-uuid` anymore; require it if your code calls the `uuid_*()` functions without the `uuid` extension
 * `UuidV1` uses a random node instead of the MAC address of the host, also when the `uuid` extension is installed

Validator
---------

 * [BC BREAK] Remove the `GroupSequence::$cascadedGroup` property, it has had no effect since the validator stopped reading it in 2014, and reading it has thrown since 7.4 typed it without a default
 * The `File` constraint checks the `extensions` and `mimeTypes` options independently, where the configured `mimeTypes` used to be narrowed to the mime types derived from the matching extension

Webhook
-------

 * Deprecate the `Symfony\Component\Webhook\Client\RequestParser` alias.
   Reference the `webhook.request_parser` service in the `service` option of the webhook routing instead, and use `#[Target('webhook')]` with `RequestParserInterface` to autowire it

Workflow
--------

 * Add argument `$context` to `WorkflowInterface::getMarking()`
 * The `workflow.security.expression_language` service is decorated by a `CompiledExpressionLanguage` when guards are configured; inject it as a `Symfony\Component\ExpressionLanguage\ExpressionLanguage`, not as a `Symfony\Component\Workflow\EventListener\ExpressionLanguage`

Yaml
----

 * A custom tag on a block scalar requires the `Yaml::PARSE_CUSTOM_TAGS` flag, as on any other value; linting such files, Ansible `!vault |` values for example, needs the `--parse-tags` option of `lint:yaml`
