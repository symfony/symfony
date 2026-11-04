CHANGELOG for 8.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 8.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v8.2.0...v8.2.1

## 8.2.0-BETA1 (2026-11-04)

### AhaSend

 * feature #65155 [AhaSend] Add support for the v2 API (rmotaln)

### AssetMapper

 * feature #65642 [AssetMapper] Add an "asset_mapper.metadata_dir" option (smnandre)
 * feature #65622 [AssetMapper] Add an importmap_entries option to stop exposing the other entrypoints (Amoifr)
 * feature #65074 [AssetMapper] Add a minimum release age for importmap updates (ousamabenyounes)
 * feature #65078 [AssetMapper] Add integrity metadata to importmaps (ousamabenyounes)
 * feature #65075 [AssetMapper] Allow requiring raw ESM packages (ousamabenyounes)
 * feature #64852 [AssetMapper] Ignore compiled assets in debug mode (smnandre)

### Bridge

 * feature #64767 [Bridge][Monolog] Implements ProcessorInterface on our processors (lyrixx)

### BrowserKit

 * feature #66896 [BrowserKit] Ignore cookies set for another domain and don't send host-only cookies to subdomains (X-Coder264)

### Cache

 * feature #66853 [Cache][HttpKernel] Add the `$target` argument to `#[Cache]` for RFC 9213 targeted cache directives (nicolas-grekas)
 * feature #66355 [Cache][Contracts][Scheduler] Report failed saves through the `$metadata` argument of `CacheInterface::get()` (nicolas-grekas)
 * feature #65824 [Cache] Add MongoDB cache adapters (GromNaN)
 * feature #65353 [Cache] Implement PruneableInterface on RedisTagAwareAdapter (nicolas-grekas)
 * feature #65351 [Cache][FrameworkBundle] Wire PdoTagAwareAdapter as cache.adapter.pdo_tag_aware (nicolas-grekas)
 * feature #58296 [Cache] Add `PdoTagAwareAdapter` (Toflar)
 * feature #65045 [Cache][FrameworkBundle] Configure the app cache with a DSN (nicolas-grekas)

### Config

 * feature #66842 [Config] Declare the elements of a node as accepted in the parent node (GromNaN)
 * feature #66033 [Config][DependencyInjection] Add NodeDefinition::inlineEnvVars() to inline env vars while the container is compiled (nicolas-grekas)
 * feature #65884 [Config][DependencyInjection][FrameworkBundle] Allow a configuration node to alias the configuration of another extension (nicolas-grekas)
 * feature #64876 [Config][FrameworkBundle] Add a way to append multiple nodes via a callback (HypeMC, nicolas-grekas)
 * feature #62125 [Config] Generate JSON schema for config (GromNaN)

### Console

 * feature #67258 [Console] Add `suggestPaths()` and `suggestDirectories()` to let the shell complete paths (nicolas-grekas)
 * feature #66950 [Console] Exit with 128 + the signal number when a signal stops the application (lyrixx)
 * feature #66770 [Console] List the accepted values of enum arguments and options in the help (nicolas-grekas)
 * feature #66622 [Console] Support lists of backed enums in #[Argument] and #[Option] (KalimeroMK)
 * feature #66020 [Console] Autowire the lock factory named "console" in LockableTrait (nicolas-grekas)
 * feature #65853 [Console] Register class-level `#[AsCommand]` groups and let the attribute list options (nicolas-grekas)
 * feature #65825 [Console] Add docker-style sub-commands, resolved as command trees from registered names (nicolas-grekas)
 * feature #65616 [Console] Allow dropping multiple files (chalasr)
 * feature #65368 [Console][DependencyInjection][HttpKernel] Support union and intersection types as controller and command arguments (HypeMC)
 * feature #63784 [Console] Auto-wrap TextDescriptor output based on terminal width (Bellangelo)
 * feature #65304 [Console] Revert "Add a `listedAt` option to list a command only from a given verbosity" (MatTheCat)
 * feature #64117 [Console][Yaml] Add a `gitlab` output format to the `lint:yaml` command (revoltek-daniel)
 * feature #61677 [Console] Allow callables for the description and help of `#[AsCommand]`, `#[Argument]` and `#[Option]` (MacDada)
 * feature #64723 [Console] Add a `listedAt` option to list a command only from a given verbosity (jprivet-dev)
 * feature #54439 [Console] Add hidden and deprecated option modes (flkasper)
 * feature #64709 [Console] Improve FileInputHelper paste detection read performance (jorgsowa)

### Contracts

 * feature #67018 [Contracts][DependencyInjection] Pass a shutdown flag to service reset methods (nicolas-grekas)
 * feature #66133 [Contracts][EventDispatcher] Add ListenerIntrospectionInterface (nicolas-grekas)

### DependencyInjection

 * feature #66667 [DependencyInjection] Let a service declare one of its methods as a service (HypeMC)
 * feature #66240 [DependencyInjection] Add `within` and `around` constraints to order decorators (nicolas-grekas)
 * feature #66012 [DependencyInjection][EventDispatcher] Order tagged services and listeners with before/after constraints (nicolas-grekas)
 * feature #66131 [DependencyInjection] Deprecate the EventDispatcherInterface autowiring alias (nicolas-grekas)
 * feature #66021 [DependencyInjection] Pass top-level extension values that are not arrays to the extension (nicolas-grekas)
 * feature #65981 [DependencyInjection] Add the "container.remove_if_missing" tag (nicolas-grekas)
 * feature #65370 [DependencyInjection][FrameworkBundle] Add `#[AutowireClassMap]` to inject a class map built from resource tags (HypeMC)
 * feature #65434 [DependencyInjection] Allow computing resource tag attributes per tagged class (HypeMC, nicolas-grekas)
 * feature #65350 [DependencyInjection] Allow ignoring classes when preloading (eissasoubhi)
 * feature #60589 [DependencyInjection] add `factory` argument in the `#[Autoconfigure]` attribute and also enabled the use of `factory` within `instanceof` conditionals (santysisi)
 * feature #64704 [DependencyInjection] Write a CACHEDIR.TAG file in the cache and build directories (shyim)
 * feature #65103 [DependencyInjection] Allow injecting a service as a lazy proxy on a per-argument basis (HypeMC)
 * feature #64980 [DependencyInjection] Add a $priority argument to the #[Required] attribute (Amoifr)
 * feature #64652 [DependencyInjection] Allow computing #[AutoconfigureTag] attributes per tagged service (nicolas-grekas)

### DoctrineBridge

 * feature #66957 [DoctrineBridge][Messenger] Add an inbox to skip the handlers of redelivered messages (nicolas-grekas)
 * feature #66714 [DoctrineBridge] Order event listeners with before/after constraints (nicolas-grekas)
 * feature #66144 [DoctrineBridge][Serializer] Denormalize into the declared Doctrine collection class (GromNaN)
 * feature #63483 [DoctrineBridge] Add the `EntityExists` constraint (evgenij-vy)
 * feature #65437 [DoctrineBridge][FrameworkBundle] Add `doctrine.orm.entity` tag and `AttributeClassesDriver` to auto-discover entities (Nayte91)
 * feature #63949 [DoctrineBridge] MapEntity - use findBy if argument type is array (Brajk19)
 * feature #63772 [DoctrineBridge] Map the `DatePoint` property types for Doctrine schema tools (wkania)
 * feature #65346 [DoctrineBridge] Deprecate `DoctrinePingConnectionMiddleware` in favor of the new `DoctrineDbalPingConnectionMiddleware`, add `DoctrineDbalTransactionMiddleware` (nicolas-grekas)
 * feature #65329 [DoctrineBridge] Deprecate `DoctrineOpenTransactionLoggerMiddleware` in favor of the new `DoctrineDbalOpenTransactionLoggerMiddleware` (MatTheCat)
 * feature #65302 [DoctrineBridge] Deprecate `DoctrineCloseConnectionMiddleware` in favor of the new `DoctrineDbalCloseConnectionMiddleware` (MatTheCat)
 * feature #63866 [DoctrineBridge] Allow using `\Closure` in `#[MapEntity]` (HypeMC)
 * feature #64469 [DoctrineBridge] Store whether a query ran on a primary (MatTheCat)
 * feature #63658 [DoctrineBridge] add IterableToArrayCollection transformer for Object Mapper (rrajkomar)

### DomCrawler

 * feature #64154 [DomCrawler] Add ChoiceFormField::selectByText() to select an option by its text content (michaelthieulin)

### ErrorHandler

 * feature #67201 [ErrorHandler] Report attributes whose class does not exist in or below the namespace they are used in (KalimeroMK)
 * feature #67037 [ErrorHandler] Add CSP nonce support to HTML error pages (nicolas-grekas)
 * feature #64766 [ErrorHandler] Redesign the exception page (javiereguiluz)

### EventDispatcher

 * feature #66129 [EventDispatcher][Messenger] Deprecate mutating the event dispatcher of the container at runtime (nicolas-grekas)
 * feature #66122 [EventDispatcher] Add CompiledEventDispatcher (nicolas-grekas)
 * feature #66022 [EventDispatcher] Allow getSubscribedEvents() to name its keys (nicolas-grekas)

### ExpressionLanguage

 * feature #66630 [ExpressionLanguage] Add `ConstantFunctionProvider` and deprecate the unrestricted `constant()` and `enum()` (nicolas-grekas)
 * feature #66652 [ExpressionLanguage][FrameworkBundle][HttpKernel][Security][SecurityBundle] Compile expressions when warming up the cache (nicolas-grekas)
 * feature #64870 [ExpressionLanguage] Add the native PHP `count()` function to the ExpressionLanguage component (payene)

### Filesystem

 * feature #65828 [Filesystem] Deprecate passing an empty string as the base path to Path::isBasePath() (Amoifr)

### Finder

 * feature #54752 [Finder] Add root-relative directory excludes via `/` prefix in `exclude()` (joshtrichards)

### Form

 * feature #66731 [Form] Map the fields of a form to the constructor of its data class (nicolas-grekas)
 * feature #66772 [Form][FrameworkBundle] Add `TypeInfoFormTypeGuesser` to guess form fields from the declared type of their property (nicolas-grekas)
 * feature #66227 [Form] Add the `sort_choices` option to `ChoiceType` (seb-jean)
 * feature #66217 [Form] Add the `entry_name` option to `CollectionType` and `PolymorphicCollectionType` (nicolas-grekas)
 * feature #65928 [Form] Add BoundsType to render a lower and an upper bound (seb-jean)
 * feature #66026 [Form] Guess "empty_data" from the type of the mapped property (nicolas-grekas)
 * feature #65515 [Form] Add an allow_array_submission option to let PRE_SUBMIT listeners transform submitted arrays (nicolas-grekas)
 * feature #60563 [Form] Add `#[AsFormType]` and `#[FormField]` attributes to define form types on data classes (WedgeSama)
 * feature #65794 [Form] Use the POST_VALIDATE event in PasswordHasherExtension (Seb33300)
 * feature #47210 [Form] Add the "form.post_validate" event (Seb33300)
 * feature #63944 [Form][TwigBridge] Render the name attribute of the <form> element through attr (Nayte91)
 * feature #65257 [Form][TwigBridge] Allow the "group_by" option of ChoiceType to return translatable labels (kira0269)
 * feature #63734 [Form] Add support for grouping and nested steps in `FormFlowType` (yceruto)
 * feature #63487 [Form] Add `PolymorphicCollectionType` for collections whose entries do not all share the same type (michaelKaefer)
 * feature #48013 [Form][TwigBridge] Render an id on the form element so form_attr references resolve (ker0x)
 * feature #63819 [Form] Add a "choice_help" option to ChoiceType (Amoifr)

### FrameworkBundle

 * feature #67237 [FrameworkBundle] Add the `debug:autoconfiguration` command (nicolas-grekas)
 * feature #66929 [FrameworkBundle][JsonSchema] Add JsonSchemaBundle (nicolas-grekas)
 * feature #66780 [FrameworkBundle][HttpKernel] Add `RateLimitValueResolver` to inject the rate limit consumed by the `RateLimit` attribute (ayyoub-afwallah)
 * feature #66820 [FrameworkBundle][Webhook] Add `KernelBrowser::sendWebhook()` and `WebhookAssertionsTrait` (nicolas-grekas)
 * feature #66801 [FrameworkBundle][RateLimiter] Reset the rate limiters between tests (nicolas-grekas)
 * feature #66789 [FrameworkBundle][Messenger] Let `MessengerAssertionsTrait` see the messages queued by previous requests (nicolas-grekas)
 * feature #66800 [FrameworkBundle][Mailer][Notifier] Let `MailerAssertionsTrait` and `NotificationAssertionsTrait` see what previous requests sent (nicolas-grekas)
 * feature #66839 [FrameworkBundle] Add the `kernel.test_state` tag to keep or reset the state of a service for the duration of a test (nicolas-grekas)
 * feature #66804 [FrameworkBundle][HttpKernel] Allow streaming the controller result as JSON in the `Serialize` attribute (nicolas-grekas)
 * feature #66805 [FrameworkBundle][SecurityBundle] Make `KernelBrowser::loginUser()` work on stateless `access_token` firewalls (nicolas-grekas)
 * feature #66803 [FrameworkBundle][HttpKernel] Allow mapping the controller result with the ObjectMapper in the `Serialize` attribute (nicolas-grekas)
 * feature #66787 [FrameworkBundle] Allow `consumeQueuedMessages()` to consume all in-memory transports (nicolas-grekas)
 * feature #66788 [FrameworkBundle][Yaml] Make `lint:yaml` validate files against their JSON Schema by default (nicolas-grekas)
 * feature #66697 [FrameworkBundle][HttpKernel] Add `LockValueResolver` to inject the lock acquired by the `Lock` attribute (Brajk19)
 * feature #66691 [FrameworkBundle][HttpKernel][SecurityBundle][Workflow] Add the `lint:expressions` command (nicolas-grekas)
 * feature #66599 [FrameworkBundle][HttpFoundation] Deprecate saving session attributes changed without calling `set()` (nicolas-grekas)
 * feature #66664 [FrameworkBundle][HttpKernel][SecurityBundle][Workflow] Deprecate `ExpressionCacheWarmer` and fail the warmup on invalid listed expressions instead (nicolas-grekas)
 * feature #66644 [FrameworkBundle][HttpKernel] Make `#[RateLimit]` reject requests before resolving controller arguments (nicolas-grekas)
 * feature #66506 [FrameworkBundle][HttpFoundation][Messenger][Security][SecurityBundle] Allow rotating the secrets that sign URIs, remember-me cookies, login links and messages (nicolas-grekas)
 * feature #66189 [FrameworkBundle] Allow using a named serializer for `#[MapRequestPayload]`, `#[MapQueryString]` & `#[Serialize]` (HypeMC)
 * feature #66176 [FrameworkBundle] Register the security expression functions on the controller expression language (tomsommer)
 * feature #65144 [FrameworkBundle][Messenger][Scheduler] Add framework.scheduler.use_messenger_routing (lyrixx)
 * feature #65900 [FrameworkBundle][Messenger] Add MessengerAssertionsTrait to test in-memory transports (nicolas-grekas)
 * feature #65997 [FrameworkBundle] Add a "configure_container" option to WebTestCase::createClient() (nicolas-grekas)
 * feature #65948 [FrameworkBundle] Move every configuration section to a bundle shipped by its own component (nicolas-grekas)
 * feature #65887 [FrameworkBundle][JsonPath][Mime][ObjectMapper][Process] Add bundles for the components that own no configuration key (nicolas-grekas)
 * feature #65750 [FrameworkBundle] Add "framework.webhook.no_private_network" and "framework.webhook.http_client" options (nicolas-grekas)
 * feature #65451 [FrameworkBundle][Mailer] Add `TrackingHeader` for per-message open/click tracking across bridges (Starfox64, nicolas-grekas)
 * feature #65427 [FrameworkBundle][HttpKernel] Allow excluding requests from being profiled (michaelthieulin)
 * feature #65428 [FrameworkBundle][WebLink] Add RFC 9264 link sets and the RFC 9652 Link-Template header (Spomky)
 * feature #64540 [FrameworkBundle][Mailer][Mime] Add PGP/MIME signing and encryption support (Spomky)
 * feature #64541 [FrameworkBundle][Mailer] Align the S/MIME listeners with the PGP/MIME design (Spomky)
 * feature #63719 [FrameworkBundle] Add security expression functions to the validator expression language (mp3000mp)
 * feature #64071 [FrameworkBundle][HttpKernel][TwigBridge] Deprecate the hinclude fragment renderer (javiereguiluz)
 * feature #63690 [FrameworkBundle][HttpFoundation] Add command to purge session data (Nayte91)
 * feature #53131 [FrameworkBundle][HttpClient] Add the retry_failed.base_uris option to http_client (Tiriel)
 * feature #65098 [FrameworkBundle] Allow enabling advisory locks on a lock resource (nicolas-grekas)
 * feature #64866 [FrameworkBundle][Messenger] Add option to disable the RejectRedeliveredMessageMiddleware (lyrixx)
 * feature #49161 [FrameworkBundle] Report unused `.env` variables in `debug:container --env-vars` (mw3e21a)
 * feature #64785 [FrameworkBundle] Add support for the HttpClient `max_connect_duration` option (alexandre-daubois)
 * feature #64018 [FrameworkBundle] Deprecate the `framework.ide` config option (Amoifr)
 * feature #64592 [FrameworkBundle] Add `--dispatchers` option to `debug:event-dispatcher` command (lyrixx)

### HttpClient

 * feature #66923 [HttpClient] Stop following redirects from https to http (nicolas-grekas)
 * feature #63781 [HttpClient] Add RecorderHttpClient to record and replay HTTP exchanges (imenezzine, nicolas-grekas)
 * feature #66557 [HttpClient] Use HTTP/2 with prior knowledge when `http_version` is `2.0` (nicolas-grekas)
 * feature #66376 [HttpClient] Allow repeating fields in the `query` and `body` options (lyrixx)
 * feature #66263 [HttpClient] Add support for the `on_trailers` request option to `GuzzleHttpHandler` (nicolas-grekas)
 * feature #66256 [HttpClient] Expose HTTP trailers as the `trailers` response info (gplanchat)
 * feature #66213 [HttpClient][WebProfilerBundle] Show the duration and the transferred sizes of each request in the profiler (michaelthieulin)
 * feature #64874 [HttpClient] Add support for the QUERY HTTP method to CachingHttpClient (ayyoub-afwallah)
 * feature #65127 [HttpClient] Allow passing a stream or a closure to HttpOptions::buffer() (nicolas-grekas)
 * feature #65123 [HttpClient] Add the extra.cache_policy option to CachingHttpClient (nicolas-grekas)

### HttpFoundation

 * feature #66705 [HttpFoundation][HttpKernel] Treat 308 like 301 for caching (Amoifr)
 * feature #66624 [HttpFoundation][HttpKernel] Forward incremental responses without buffering them in `HttpClientKernel` (nicolas-grekas)
 * feature #65390 [HttpFoundation] Expand PRIVATE_SUBNETS with five not-globally-reachable ranges (Synchro)
 * feature #63804 [HttpFoundation][HttpKernel] Handle any +json/+xml media type in #[MapRequestPayload] (Amoifr)
 * feature #49227 [HttpFoundation] Constraint ResponseHeaderSame now shows the actual header value (jschaedl)
 * feature #65282 [HttpFoundation] Add targeted cache-control support (RFC 9213) (nicolas-grekas)
 * feature #65162 [HttpFoundation] Reject a Cookie with an invalid __Secure-/__Host- prefix (dunglas)
 * feature #64169 [HttpFoundation] Add `ParameterBag::filterCallback()` (Amoifr)
 * feature #64408 [HttpFoundation] Allow creating single-use signed urls (kbond)
 * feature #64455 [HttpFoundation] Deprecate not passing an expiry to `UriSigner::sign()` (kbond)

### HttpKernel

 * feature #67563 [HttpKernel][Security] Let controllers handle the failures of their attributes (kbond)
 * feature #67051 [HttpKernel] Return HTTP 204 for controllers declaring void (Koc)
 * feature #66784 [HttpKernel][Lock] Allow `{name}` placeholders in the keys of the `Lock` and `RateLimit` attributes (nicolas-grekas)
 * feature #66515 [HttpKernel][Lock] Add `#[Lock]` attribute to reject or delay concurrent requests (Brajk19)
 * feature #66641 [HttpKernel] Allow setting a condition when the `#[RateLimit]` attribute should be applied (HypeMC)
 * feature #66595 [HttpKernel] Add HttpException classes for 402, 408, 413, 416, 417, 424, 425, 451 and 501 (bigboss86, nicolas-grekas)
 * feature #65600 [HttpKernel][RateLimiter] Add option to expose `X-RateLimit-*` headers for the `#[RateLimit]` attribute (ayyoub-afwallah)
 * feature #65573 [HttpKernel][FrameworkBundle] Add the cache_status option to emit the RFC 9211 Cache-Status header (Amoifr)
 * feature #64368 [HttpKernel] Deserialize a JSON query parameter with #[MapQueryString(key:)] (mudassaralichouhan)
 * feature #64210 [HttpKernel][WebProfilerBundle] Add dump icon in result list (MrYamous)
 * feature #64447 [HttpKernel] Add `#[AsControllerAttributeListener]` (lyrixx)

### Intl

 * feature #67532 [Intl] Add a `prune` script to remove the data of unused locales (nicolas-grekas)

### JsonSchema

 * feature #66930 [JsonSchema] Allow PHP 8.2 (nicolas-grekas)
 * feature #66685 [JsonSchema] Add the component (soyuka)

### JsonStreamer

 * feature #66568 [JsonStreamer][Mailer][Notifier][SecurityBundle][Webhook] Deprecate autowiring aliases named after implementations (nicolas-grekas)
 * feature #64714 [JsonStreamer] Add UID value object support (mtarld)
 * feature #64190 [JsonStreamer] Add a "cache_variant" option to the generated cache file name (soyuka)
 * feature #63743 [JsonStreamer] Add `BcMath\Number` and `GMP` value object support (mtarld)

### KeyManagement

 * feature #67691 [KeyManagement] Add AES-GCM-SIV support to KMIP (X-Coder264)
 * feature #67690 [KeyManagement] Add ChaCha20-Poly1305 support to KMIP (X-Coder264)
 * feature #67689 [KeyManagement] Add KMIP 2.1 support (X-Coder264)
 * feature #66637 [KeyManagement] Authenticate the context of encrypted Doctrine columns (Spomky)
 * feature #66920 [KeyManagement] Add a Scaleway Key Manager bridge (daFish)
 * feature #66918 [KeyManagement] Let the HashiCorp Vault bridge get its token from a provider (daFish)
 * feature #66635 [KeyManagement] Let a data key store bind its wrapping context at the backend (Spomky)
 * feature #66639 [KeyManagement] Make reading a self-contained payload a stated choice (Spomky)
 * feature #66806 [KeyManagement] Add the `Encrypted` attribute to encrypt Doctrine ORM columns (nicolas-grekas)
 * feature #66766 [KeyManagement] Register the encrypted Doctrine DBAL types from the bundle configuration (nicolas-grekas)
 * feature #66368 [KeyManagement] Add KMIP bridge (X-Coder264)
 * feature #66277 [KeyManagement] Refresh rejected KMS tokens after 401 responses (X-Coder264)
 * feature #66229 [KeyManagement] Memoize Flysystem keys with optional reset (X-Coder264)
 * feature #66165 [KeyManagement] Add CompositeKms, a client wrapping under several providers (Spomky)
 * feature #64052 [KeyManagement] Add a new component for Key Management Systems (Spomky)

### Ldap

 * feature #67803 [Ldap][Security][SecurityBundle] Hash the presented password when the user is unknown, disabled or has no password (nicolas-grekas)
 * feature #67233 [Ldap] Send only the changed attributes when updating an entry (nicolas-grekas)
 * feature #64976 [Ldap][SecurityBundle] Add an option to check only LDAP users against the LDAP server (nicolas-grekas)

### Lock

 * feature #66526 [Lock] Add flock+remove DSNs to remove the lock file on release (GromNaN)
 * feature #65787 [Lock][Messenger][Notifier] Add the "ssl" DSN option to select the HTTP scheme (MarcinOrlowski, nicolas-grekas)
 * feature #65021 [Lock] Allow advisory locks when reusing a PDO/DBAL connection in StoreFactory (saifulferoz)
 * feature #64987 [Lock] Add DoctrineDbalMysqlStore for advisory locking (ryanpon-payjoy)
 * feature #64757 [Lock] Add MysqlStore for advisory locking (ryanpon-payjoy)

### Mailer

 * feature #66777 [Mailer] Add Zoho CPaaS bridge (Taldres)
 * feature #66086 [Mailer] Support tenant configuration via the DSN and the X-SES-TENANT header (DjLeChuck)
 * feature #65879 [Mailer][Sweego] Add support for CC and BCC recipients (lazerg)
 * feature #65826 [Mailer] Add PufferPost bridge (JeroenMoonen)
 * feature #65819 [Mailer] Add RemoteTemplateEmail support to the remaining bridges with provider-hosted templates (nicolas-grekas)
 * feature #64782 [Mailer] Add RemoteTemplateEmail to send emails rendered from provider-hosted templates (Orkin)
 * feature #65771 [Mailer][Mime] Add RFC 6854 group support to mailbox list headers (Spomky)
 * feature #65791 [Mailer] Support tags in the MailerSend transports (thomas-bw)
 * feature #65793 [Mailer][Notifier] Deprecate reading a non-boolean value with `Dsn::getBooleanOption()` (nicolas-grekas)
 * feature #65672 [Mailer] Add MailKite bridge (bucabay)
 * feature #65536 [Mailer][Cloudflare] Add an SMTP transport (vadage)
 * feature #65420 [Mailer] Add DSN param `timeout` to configure the SMTP connect/read/write timeout (anktx)
 * feature #64056 [Mailer] Add a bridge for the Cloudflare email service (vadage)
 * feature #65355 [Mailer] Add support for Scaleway webhooks (nicolas-grekas)
 * feature #59985 [Mailer] Add support for rate limited mailer transports (fritzmg)
 * feature #62371 [Mailer][Mandrill] Add `return_path_domain` to payload (andrehoong-pixieset)
 * feature #63813 [Mailer] Add Gmail API transport with OAuth2 service account authentication (Amoifr)
 * feature #63802 [Mailer][Azure] Add webhook support for Azure Communication Services Email events (Amoifr)
 * feature #64916 [Mailer] Add TurboSMTP bridge (NewtTheWolf)
 * feature #64839 [Mailer][BrevoMailer] Allow configuring the SMTP port through the DSN (thomas2411)
 * feature #64623 [Mailer] Reorder EsmtpTransport authenticators to prefer PLAIN over obsolete LOGIN (tabascoterrier)

### Messenger

 * feature #66964 [Messenger] Let workers stop consuming a transport for a while after its messages fail (HypeMC)
 * feature #66953 [Messenger] Support sending messages to specific Beanstalkd tubes (Nikolay-Kurdoglo)
 * feature #66898 [Messenger] Deprecate signing messages per type (nicolas-grekas)
 * feature #66870 [Messenger] Add a --stop-when-empty option to messenger:consume (KalimeroMK)
 * feature #66875 [Messenger] Sign every header written by the decorated serializer (nicolas-grekas)
 * feature #66873 [Messenger] Let `InteropSerializer` exchange stamps as plain headers (nicolas-grekas)
 * feature #66715 [Messenger] Order handlers with before/after constraints (nicolas-grekas)
 * feature #66695 [Messenger] Allow configuring the lock factory of the deduplication middleware (HypeMC)
 * feature #66689 [Messenger] Add `WorkerRestarter` and let `messenger:stop-workers` stop the workers of some transports only (nicolas-grekas)
 * feature #66574 [Messenger] Add InteropSerializer to exchange messages with another application (nikophil)
 * feature #66627 [Messenger] Send batches with the Redis, MongoDB, AMPHP SQL and AMQP transports, and through the outbox (JoppeDC, nicolas-grekas)
 * feature #66646 [Messenger] Allow specifying additional AMQP queue bindings (wouterj)
 * feature #66586 [Messenger] Add the `messages` and `unwrap_exceptions` options of buses (nicolas-grekas)
 * feature #66590 [Messenger] Add `#[AsMessageMiddleware]` to add middleware to a bus (nicolas-grekas)
 * feature #66450 [Messenger][MongoDb] Listen to multiple queues in a single request (GromNaN)
 * feature #66564 [Messenger] Add a `--middleware` option to the `debug:messenger` command (ker0x)
 * feature #65917 [Messenger] Add DispatchOnFailureStamp to dispatch a message when another one fails (nicolas-grekas)
 * feature #65903 [Messenger] Add ChainStamp to dispatch messages one after another (nicolas-grekas)
 * feature #66505 [Messenger][Scheduler] Add a `sign` option to transports to sign every message (nicolas-grekas)
 * feature #65899 [Messenger] Let a flow carry its context in stamps: propagation, handler arguments and identity (nicolas-grekas)
 * feature #66512 [Messenger][Scheduler] Replace `RedispatchMessageHandler` by a non-sendable `RedispatchStamp` (nicolas-grekas)
 * feature #66192 [Messenger] Allow using a named serializer for `messenger.transport.symfony_serializer` (HypeMC)
 * feature #65902 [Messenger] Add retry and failure transport support to the sync transport (nicolas-grekas)
 * feature #66002 [Messenger] Extract the failed-message logic out of the console commands (nicolas-grekas)
 * feature #65924 [Messenger][Amqp] Add a prefetch_count option to consume messages instead of fetching them one by one (nicolas-grekas)
 * feature #65901 [Messenger] Add an outbox option to transports (nicolas-grekas)
 * feature #65898 [Messenger] Add a transport option to #[AsMessageHandler] (nicolas-grekas)
 * feature #64072 [Messenger] Add routing information to the `debug:messenger` command (ckrack)
 * feature #65633 [Messenger] Force quit `messenger:consume` and `messenger:failed:retry` on repeated `SIGINT` (HypeMC)
 * feature #63517 [Messenger] Add SQS system attributes of received messages to AmazonSqsReceivedStamp (maxbaldanza)
 * feature #64892 [Messenger] Add AMPHP SQL messenger transport (fabpot)
 * feature #65641 [Messenger] Add claim check support (Toflar)
 * feature #65760 [Messenger] Support raw Redis stream messages (ousamabenyounes)
 * feature #65605 [Messenger] Stop deduplicating the messages sent to a FIFO SQS queue on their content by default (Amoifr)
 * feature #64829 [Messenger] Add messenger:show command to inspect pending messages (payene)
 * feature #62128 [Messenger] Add MongoDB bridge (Jean85)
 * feature #52425 [Messenger] Dispatch events before & after each handler (BenMorel)
 * feature #64621 [Messenger] Add an optional LoggingMiddleware logging processing time and memory usage (mariecharles)
 * feature #47975 [Messenger][Amqp] Pass the routing key to the serializer when decoding (jenkoian)
 * feature #57757 [Messenger] Prioritize receivers via transport configuration (Peter van der Wal)
 * feature #65343 [Messenger] Add --redispatch to messenger:failed:retry (eissasoubhi)
 * feature #63650 [Messenger] Add a `--concurrency` option to the `messenger:consume` command to process messages in parallel (nicolas-grekas)
 * feature #62080 [Messenger][Sqs] Add SQS fair queues support (Greg0)
 * feature #64603 [Messenger][Routing] add `RequestContext::runWith()` to temporarily generate URLs for another context (Jean-Beru)
 * feature #65168 [Messenger][Amqp] Round delays up to bound the number of delay queues (nicolas-grekas)
 * feature #58575 [Messenger] Allow auto creation of sqs queue (kevinrudde)
 * feature #65143 [Messenger] Use configured senders when RedispatchMessage has no transport name (lyrixx)
 * feature #65096 [Messenger] Filter failed messages by class and failure time (nicolas-grekas)
 * feature #64988 [Messenger] Add AMQP transport keepalive support (ousamabenyounes)
 * feature #64952 [Messenger] Add support for serialized type name aliases in `#[AsMessage]` attribute (yceruto)

### Mime

 * feature #66484 [Mime] Keep the explicit encoding of parts in FormDataPart (michaelthieulin)
 * feature #65381 [Mime] Add `AbstractPart::setContentTypeParameter()` (nicolas-grekas)
 * feature #64066 [Mime] Add support for binary Content-Transfer-Encoding (ousamabenyounes)

### MonologBridge

 * feature #66699 [MonologBridge] Filter the logs written to the console by channel (HypeMC)

### Notifier

 * feature #66854 [Notifier] Use the global `From` header of the mailer as the sender of the email channel (nicolas-grekas)
 * feature #66752 [Notifier] Add an autowiring alias for `AdminRecipientsProviderInterface` (nicolas-grekas)
 * feature #65990 [Notifier] Support per-message recipient in the LineBot bridge (nicolas-grekas)
 * feature #66018 [Notifier] Deprecate the LineNotify bridge (Amoifr)
 * feature #65581 [Notifier] Add AdminRecipientsProviderInterface (nicolas-grekas)
 * feature #64077 [Notifier][Novu] Add `$context` to `NovuOptions` and deprecate recipient-level overrides (herman-aka-wouter)
 * feature #60205 [Notifier][Firebase] Add Firebase v1 API support (Vojtech Smejkal)
 * feature #64003 [Notifier] Add SMS Proxima bridge (SMS-Proxima)
 * feature #60310 [Notifier][Slack] Add SlackPlainTextInputBlock for Slack message (camilleislasse)
 * feature #65178 [Notifier] Add the Threads bridge (matyo91)
 * feature #65179 [Notifier] Add the Instagram bridge (matyo91)
 * feature #65102 [Notifier] Add the Facebook Page bridge (matyo91)
 * feature #64998 [Notifier][LinkedIn] Add organization author support via DSN option (matyo91)
 * feature #59784 [Notifier][Bluesky] Add support for tag facets (romainneutron)
 * feature #64883 [Notifier] Add the WhatsApp bridge (recchia)

### ObjectMapper

 * feature #67578 [ObjectMapper] Pass the names of the mapped properties to mapping-aware transformers (nicolas-grekas)
 * feature #67141 [ObjectMapper] Merge the objects that repeated `#[Map]` attributes map to the same property (nicolas-grekas)
 * feature #66154 [ObjectMapper] Add property metadata factories (soyuka)
 * feature #65970 [ObjectMapper] Infer nested target class from destination property type (iz-ahmad)
 * feature #64220 [ObjectMapper] Add MappingAwareTransformCallableInterface (ousamabenyounes)
 * feature #62392 [ObjectMapper] Enable converting backed enums to their scalar values (Nayte91)

### PasswordHasher

 * feature #67025 [PasswordHasher] Deprecate built-in legacy hashers as migration targets (nicolas-grekas)

### PhpUnitBridge

 * feature #66858 [PhpUnitBridge] Make ClockMock follow the global MockClock (nicolas-grekas)
 * feature #66175 [PhpUnitBridge] Add DebugClassLoader issue trigger resolver (X-Coder264)

### Process

 * feature #66949 [Process] Add `Process::inheritStdio()` to run a process with the standard streams of the current process (lyrixx)

### PropertyAccess

 * feature #65297 [PropertyAccess] Add wildcard reads (Boulea7)

### PropertyInfo

 * feature #63998 [PropertyInfo][Serializer] Add opt-in default groups (BastienClement)
 * feature #59246 [PropertyInfo] Gather data from property hooks in ReflectionExtractor (xavierleune)
 * feature #63864 [PropertyInfo][Serializer] Enable using `#[WithAccessors]` with the serializer (HypeMC)
 * feature #59529 [PropertyInfo] Allow defining accessors and mutators via an attribute (HypeMC)

### RateLimiter

 * feature #63389 [RateLimiter] Allow overriding the key of a compound limiter's sub-limiters (NicolasJourdan)
 * feature #60084 [RateLimiter] Add `RateLimiterBuilder` (kbond)
 * feature #65161 [RateLimiter] Dispatch a RateLimitExceededEvent when the #[RateLimit] attribute rejects a request (mariecharles)

### RemoteEvent

 * feature #65038 [RemoteEvent][Notifier] Add SMS engagement events (ousamabenyounes)
 * feature #63698 [RemoteEvent] Allow repeating `#[AsRemoteEventConsumer]` (ostrolucky)

### Routing

 * feature #67247 [Routing][TwigBundle][FrameworkBundle] Add shortcuts for template and redirect routes (javiereguiluz)
 * feature #66004 [Routing] Add addCondition() to combine a condition with the existing ones (nicolas-grekas)
 * feature #63112 [Routing][SecurityBundle] Add a `firewall` option to route definitions (tilaven)
 * feature #65073 [Routing] Allow defining default query parameters (ousamabenyounes)

### Scheduler

 * feature #66797 [Scheduler] Add ExcludeTimeOfDayTrigger to exclude a daily time window (KalimeroMK)
 * feature #66782 [Scheduler] Add a "stamps" option to #[AsCronTask] and #[AsPeriodicTask] (KalimeroMK)
 * feature #66670 [Scheduler] Add the `schedules` configuration to make a schedule stateful or locked (nicolas-grekas)
 * feature #65383 [Scheduler] Deprecate Schedule::with() (nicolas-grekas)
 * feature #64665 [Scheduler] Add env option to #[AsCronTask] and #[AsPeriodicTask] (lacatoire)
 * feature #64238 [Scheduler] Show "Next Run In" column in debug:scheduler command (janklan)

### Security

 * feature #67140 [Security][SecurityBundle] Add the entry_point_path firewall option (bigboss86)
 * feature #67041 [Security] Retain the referer after failed authentication (nicolas-grekas)
 * feature #66999 [Security] Start a re-authentication for remember-me users when a vote asks for it (nicolas-grekas)
 * feature #66786 [Security][TwigBridge] Add `logout_button()`, `impersonation_button()` and `impersonation_exit_button()` (nicolas-grekas)
 * feature #66718 [Security][SecurityBundle] Order firewall listeners with before/after constraints (nicolas-grekas)
 * feature #66634 [Security][SecurityBundle] Allow `#[IsGranted]` to vote on `#[MapRequestPayload]` arguments (nicolas-grekas)
 * feature #65628 [Security] Dispatch RateLimitExceededEvent from LoginThrottlingListener (mariecharles)
 * feature #66618 [Security] Let an application reach an endpoint of its OIDC provider through the client (Spomky)
 * feature #66196 [Security][SecurityBundle] Back-Channel Logout for oidc_login (Spomky)
 * feature #66465 [Security][SecurityBundle] Accept a DPoP-bound access token only from a request that proves its key (Spomky)
 * feature #66239 [Security][SecurityBundle] Bind what an OIDC provider issues to a key the client holds (DPoP) (Spomky)
 * feature #66238 [Security][SecurityBundle] Authenticate the OIDC client with its TLS certificate, per RFC 8705 (Spomky)
 * feature #66234 [Security] Name the issuer as the audience of an OIDC client assertion (Spomky)
 * feature #66495 [Security] Make FormLoginAuthenticator a re-authentication entry point (kbond)
 * feature #66437 [Security][SecurityBundle] Let the discovery endpoints of the oidc token handler be pinned to their issuer (thomas-chauchefoin-tob)
 * feature #66242 [Security] Let voters tell which denials a re-authentication can cure (nicolas-grekas)
 * feature #66195 [Security] Read an OIDC authorization response the provider posted (form_post) (Spomky)
 * feature #66184 [Security] Record the OIDC `acr` claim and name the attribute that started a re-authentication (nicolas-grekas)
 * feature #66136 [Security] Run a user checker when the user is refreshed from the session (kbond)
 * feature #66069 [Security] Add AuthenticationMethodBadge, for an authenticator to state which methods it verified (nicolas-grekas)
 * feature #66066 [Security] Add IS_AUTHENTICATED_VERY_RECENTLY, decided by the trust resolver (nicolas-grekas)
 * feature #66065 [Security] Record which authentication methods were proven, and when (nicolas-grekas)
 * feature #66117 [Security] Add `CheckRefreshedUserEvent` to extend the user-change checks (kbond)
 * feature #66055 [Security] Let OidcLoginAuthenticator start a re-authentication (nicolas-grekas)
 * feature #66047 [Security][SecurityBundle] Allow tailoring the OIDC authorization request per request (yceruto)
 * feature #66016 [Security][SecurityBundle] Add a re-authentication entry point (nicolas-grekas)
 * feature #66035 [Security] Decide IS_AUTHENTICATED_RECENTLY through the trust resolver (nicolas-grekas)
 * feature #66015 [Security] Stamp auth_time from the OIDC ID token claim (nicolas-grekas)
 * feature #66014 [Security][SecurityBundle] Add IS_AUTHENTICATED_RECENTLY to require a fresh authentication (nicolas-grekas)
 * feature #65916 [Security][SecurityBundle] Add the private_key_jwt and client_secret_jwt client authentication methods (Spomky)
 * feature #65915 [Security][SecurityBundle] Let the oidc token handler answer for several audiences (Spomky)
 * feature #65895 [Security][SecurityBundle] Inject the OAuth2 client authentication into the OIDC client (Spomky)
 * feature #65897 [Security][SecurityBundle] Read the introspection signature keys from the authorization server metadata (nicolas-grekas)
 * feature #65866 [Security][SecurityBundle] Add RFC 9701 signed introspection responses (Spomky)
 * feature #65865 [Security][SecurityBundle] Make the OAuth2 introspection token handler usable (Spomky)
 * feature #65874 [Security][SecurityBundle] Require OAuth2 scopes on resources protected by an access token (Spomky)
 * feature #65864 [Security][SecurityBundle] Serve the RFC 9728 protected resource metadata of an access_token firewall (Spomky)
 * feature #65875 [Security] Renew the OIDC access token with the refresh token grant (Spomky)
 * feature #65862 [Security][SecurityBundle] Check the RFC 9068 "at+jwt" type of OIDC access tokens (Spomky)
 * feature #65821 [Security] Add a `current_user()` function to the expression language provider (seb-jean)
 * feature #65827 [Security] Deprecate passing more than one attribute to AccessDecisionManager::decide() (Amoifr)
 * feature #65839 [Security] Tell why an authenticator did not support the request in the profiler (nicolas-grekas)
 * feature #61938 [Security] Allow `AuthorizationChecker::isGrantedForUser` to check for guest permissions (aschempp)
 * feature #65817 [Security][SecurityBundle] Add OIDC claims source, user-identifier mapping and RP-Initiated Logout (welcoMattic)
 * feature #65814 [Security][SecurityBundle] Add OIDC PKCE, max_age, authorization-request and start-route options (welcoMattic)
 * feature #65798 [Security] Verify the OIDC ID token signature against the provider JWKS (welcoMattic)
 * feature #65799 [Security] Add OIDC token-endpoint client authentication methods (welcoMattic)
 * feature #64954 [Security] Add an OIDC Authorization Code Flow authenticator (welcoMattic)
 * feature #64271 [Security] Allow passing extra signed parameters in the login link URL (janklan)
 * feature #52099 [Security] Allow wildcards as placeholders in the role hierarchy (squrious)
 * feature #65279 [Security][SecurityBundle] Support decorating custom authentication result handlers (nicolas-grekas)
 * feature #64879 [Security] Add `path` and CSRF options to `switch_user` (kbond)
 * feature #65137 [Security] Throw the status-code-specific HttpException for #[IsGranted] (lazerg)
 * feature #65129 [Security] Add a form helper for the logout (nicolas-grekas)
 * feature #65124 [Security] Serve the CSRF token id of a firewall through the manager it configures (nicolas-grekas)
 * feature #63867 [Security] Improve authentication debugging in the profiler (Amoifr)
 * feature #57622 [Security] Throw a forbidden exception when the IsCsrfTokenValid attribute fails (eltharin)
 * feature #46320 [Security] Allow disabling redirect on logout (jvasseur)

### SecurityBundle

 * feature #67382 [SecurityBundle] Add the discovery_uri option to the oidc_login authenticator (Spomky)
 * feature #66882 [SecurityBundle][Validator] Lower password hashing costs and disable `not_compromised_password` when `framework.test` is enabled (nicolas-grekas)
 * feature #66881 [SecurityBundle] Add `security.route_loader` to load the routes of all firewalls (nicolas-grekas)
 * feature #66749 [SecurityBundle] Add the `mermaid` format to `debug:roles` and deprecate `debug:security:role-hierarchy` (nicolas-grekas)
 * feature #66619 [SecurityBundle] Make the OIDC client of an `oidc_login` firewall autowirable (nicolas-grekas)
 * feature #65910 [SecurityBundle] Add an "http_client" option to the "oidc_login" authenticator (Spomky)
 * feature #65345 [SecurityBundle] Add a debug:roles command to inspect the role hierarchy (squrious)
 * feature #61786 [SecurityBundle] add role hierarchy graph to profiler security panel (damienfern)
 * feature #65056 [SecurityBundle] Deprecate access control rules voting on many attributes (MatTheCat)
 * feature #64875 [SecurityBundle] Deprecate the unused `remember_me` authenticator option (kbond)

### SecurityHttp

 * feature #64632 [SecurityHttp] Make OidcTokenHandler `allowedTimeDrift` configurable (jprivet-dev)

### Serializer

 * feature #66254 [Serializer] Deprecate denormalizing a property using its raw name when a name converter is configured (mdalikadar)
 * feature #66005 [Serializer] Resolve generic template types during denormalization (yceruto)
 * feature #65393 [Serializer] Support extensible discriminator map via child attribute (mttsch)
 * feature #58604 [Serializer] Add a `force_collection` option to `XmlEncoder` (Alexandre Gérault)
 * feature #58236 [Serializer] Support serialized names and paths configuration per group (daniser)
 * feature #57166 [Serializer] Add `AbstractNormalizer::IGNORED_GROUPS` to make it possible to ignore groups during serialization (zim32)
 * feature #65071 [Serializer] Add a "skip_invalid_attributes" denormalization option (nicolas-grekas)
 * feature #63811 [Serializer] Add support for denormalizing arrays of union types (Amoifr)
 * feature #63803 [Serializer] Add XmlEncoder::BOOLEAN_REPR context option (Amoifr)
 * feature #64089 [Serializer] Use the discriminator property when several types map to the same class (ousamabenyounes)
 * feature #64650 [Serializer] Allow associative `csv_headers` to map and reorder columns when encoding (nicolas-grekas)
 * feature #63971 [Serializer] Deprecate denormalizing non-list arrays into list-typed properties (Jean-Beru)

### String

 * feature #65842 [String] Add a $regexp argument to the case conversion methods (nicolas-grekas)

### TelegramNotifier

 * feature #64574 [TelegramNotifier] Add support for editing media messages (michaelthieulin)

### Translation

 * feature #66690 [Translation][TwigBridge][TwigBundle] Add `HtmlTranslator` and the `trans_html` filter (Kocal)
 * feature #66284 [Translation] Extract messages from match expressions, interpolated strings, variables and method return values (guillaume-sainthillier)
 * feature #66051 [Translation] extract messages from ternary and null-coalescing expressions (guillaume-sainthillier)
 * feature #66028 [Translation] Make `CrowdinProvider::write()` add the locales missing from the project (Amoifr)
 * feature #66007 [Translation] Make `PhraseProvider::read()` fetch every domain and locale when passed none (Amoifr)
 * feature #65802 [Translation] Add translation:update-xliff-sources command (squrious)
 * feature #65436 [Translation] Re-add the POEditor provider bridge (Amoifr)
 * feature #64977 [Translation] Fix fuzzy translations and message context in PO files (javiereguiluz)
 * feature #65269 [Translation] Make `CrowdinProvider::read()` fetch all domains when passed none (MatTheCat)
 * feature #64873 [Translation] Make `CrowdinProvider::read()` fetch every locale when passed none (MatTheCat)
 * feature #64379 [Translation] Allow defining a mapping between a Loco tag filter and a domain (MatTheCat)
 * feature #62023 [Translation] Keep the XLIFF source element on a load and dump round trip (mauricedefelicec24)
 * feature #64660 [Translation] Update `CrowdinProvider` HTTP client’s base URI (MatTheCat)
 * feature #64594 [Translation] Make `LocoProvider::read()` fetch every locale when passed none (MatTheCat)

### Tui

 * feature #67652 [Tui] Add support for the Program Status Protocol (OSC 7501) (fabpot)
 * feature #66244 [Tui] Add Tui::getWidgetRect() (gplanchat)
 * feature #66550 [Tui] Reject a key id naming an unknown key when creating Keybindings (phcorp)
 * feature #66538 [Tui] Add the super, hyper and meta modifiers to key ids (phcorp)
 * feature #65857 [Tui] Support bottom and right tab headers (fabpot)
 * feature #64132 [Tui] Add tab widget (fabpot, nicolas-grekas)
 * feature #65418 [Tui] Keep a widget's listeners across detach, and add off() to release them (sadiqk2, nicolas-grekas)
 * feature #64270 [Tui] Add CollapsibleWidget (sblondeau)
 * feature #64218 [Tui] Add KeyBindingWidget (sblondeau)
 * feature #65384 [Tui] Dispatch paste lifecycle events (fabpot)
 * feature #65424 [Tui] Add AnsiUtils::walkCells(), AnsiUtils::sliceToWidth() and AbstractWidget::postRender() (nicolas-grekas)
 * feature #65283 [Tui] Reuse unchanged lines across frames (fabpot, nicolas-grekas)
 * feature #64881 [Tui] Add `multiselect` option to `SelectListWidget` (HypeMC)
 * feature #65175 [Tui] Add attachChild() and detachChild() to AbstractWidget (nicolas-grekas)

### TwigBridge

 * feature #66988 [TwigBridge] Extract the labels and help messages passed to form functions (nicolas-grekas)
 * feature #66774 [TwigBridge][TwigBundle] Add a `sign_url` filter to sign absolute URLs (nicolas-grekas)
 * feature #63986 [TwigBridge] Add a "for _self" modifier to the trans_default_domain tag (mpdude)
 * feature #65040 [TwigBridge] Add normalize filter (ousamabenyounes)

### TypeInfo

 * feature #66671 [TypeInfo] Add `Type::getMismatches()` to tell where and why a value is not accepted (nicolas-grekas)
 * feature #66361 [TypeInfo][Serializer][JsonStreamer] Add `Type::map()` (mtarld)
 * feature #64130 [TypeInfo] Add support for covariant templates (gsteel)

### Uid

 * feature #63941 [Uid] Add `createBoundaries()` to `Ulid`, `UuidV6` and `UuidV7` (thePanz)

### Validator

 * feature #66301 [Validator] Make `Constraint` objects invokable (rela589n)
 * feature #66134 [Validator] Allow Valid in Sequentially (X-Coder264)
 * feature #66143 [Validator] Add the Audio constraint for validating audio files (Valmonzo, nicolas-grekas)
 * feature #66000 [Validator] Add #[WithHttpStatus(422)] to ValidationFailedException (nicolas-grekas)
 * feature #65110 [Validator] Add BcMath\Number support to comparison and Range constraints (saifulferoz)
 * feature #63557 [Validator] Accept the `::class` constant of the annotated class in `GroupSequence` (naxvog)
 * feature #48951 [Validator] Handle object properties in Unique validator (plfort)
 * feature #64779 [Validator] Add a `message` option to the `Callback` constraint (javiereguiluz)
 * feature #64641 [Validator] Allow cascading the current group sequence group to referenced objects (Amoifr)
 * feature #65104 [Validator] Let the groups of Valid only trigger the cascade (nicolas-grekas)
 * feature #64367 [Validator] Allow non-string values in ConstraintViolationBuilderInterface::setParameter() (Amoifr)
 * feature #64538 [Validator] Add Cron constraint to validate cron expressions (ErfanMomeniii)

### VarDumper

 * feature #64947 [VarDumper] Make dump toggles keyboard accessible (Nitram1123)

### Webhook

 * feature #66753 [Webhook] Make `webhook.routing.*.service` default to `webhook.request_parser` (nicolas-grekas)
 * feature #62958 [Webhook] Add timestamp header and Standard Webhooks signature support (nicolas-grekas)

### WebProfilerBundle

 * feature #67042 [WebProfilerBundle] Capture AJAX requests started before toolbar initialization (nicolas-grekas)
 * feature #64853 [WebProfilerBundle] Make the profiler panels keyboard and screen reader accessible (Nitram1123)
 * feature #60505 [WebProfilerBundle] display profiler url in logs (jeremyFreeAgent)

### Workflow

 * feature #61935 [Workflow] Allow to define workflow with PHP attributes (lyrixx)
 * feature #64221 [Workflow] Add $disabledEvents to permanently silence events at the workflow level (ousamabenyounes)

### Yaml

 * feature #66341 [Yaml] Handle tags on block scalars as on other values (nicolas-grekas)
 * feature #66328 [Yaml] Add support for parsing a block scalar as the root node (SulimanAbdulrazzaq)
 * feature #65394 [Yaml][FrameworkBundle] Add JSON Schema validation to the `lint:yaml` command (GromNaN)
 * feature #65243 [Yaml] Add support for int, null, bool yaml tags including quotes (dmitryuk)

### Other

 * feature #65386  Let source value resolvers stage a value for the resolvers that follow them (nicolas-grekas)
 * feature #64400  allow set region SweegoOptions (qdequippe)
