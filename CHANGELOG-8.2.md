CHANGELOG for 8.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 8.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v8.2.0...v8.2.1

## 8.2.0 (2026-11-28)

### BrowserKit

 * bug #68478 [BrowserKit] Fix meta refresh parsing to follow the HTML spec (nicolas-grekas)
 * bug #68434 [BrowserKit] Strip tabs and newlines from meta refresh URLs (tandrezone)
 * bug #68433 [BrowserKit] Make `CookieJar` match cookie domains case-insensitively (tandrezone)

### Cache

 * bug #68477 [Cache] Fix NullAdapter and commit() failing on an uninitialized lazy ghost (nicolas-grekas)
 * bug #68456 [Cache] Fix ChainAdapter failing on an uninitialized lazy ghost (lazerg)
 * minor #68475 [Cache] Use attributes to skip ChainAdapterLazyGhostTest before PHP 8.4 (nicolas-grekas)

### DependencyInjection

 * bug #68440 [DependencyInjection] Resolve class aliases when matching attribute autoconfigurators (dhrupo)
 * bug #68415 [DependencyInjection] Compare service IDs strictly when resolving circular references (malekkadri)

### ErrorHandler

 * bug #68487 [ErrorHandler][WebProfilerBundle] Don't collapse a toggle when clicking a button or a field inside it (prateekbhujel)

### ExpressionLanguage

 * bug #68481 [ExpressionLanguage] Fix compiling an unknown name followed by `??` inside an operation (nicolas-grekas)
 * bug #68476 [ExpressionLanguage] Fix dumping nodes that don't parse back to the same expression (nicolas-grekas)
 * bug #68449 [ExpressionLanguage] Preserve hash keys when dumping array nodes (malekkadri)
 * bug #68416 [ExpressionLanguage] Preserve numeric strings when dumping constants (malekkadri)

### Filesystem

 * bug #68460 [Filesystem] Match complete extensions in Path::getFilenameWithoutExtension() (ahbaqdadi)

### Finder

 * bug #68468 [Finder] Recognize missing PCRE pattern modifiers (malekkadri)

### Form

 * minor #68496 [Form][Validator] Review Armenian (hy) translations (dbuhonov)
 * minor #68419 [Form][Security][Validator] Add Standard Moroccan Amazigh (zgh) translations (malekkadri)

### FrameworkBundle

 * bug #68469 [FrameworkBundle] Fix static analysis of the deprecated `AsRoutingConditionService` attribute (nicolas-grekas)

### HtmlSanitizer

 * bug #68441 [HtmlSanitizer] Reject relative URLs with a colon in the first path segment when parsing (vasikmansuri)

### HttpClient

 * bug #68493 [HttpClient] Fix CPU usage of AmpHttpClient with many concurrent streams (jwage)
 * bug #68437 [HttpClient][KeyManagement][Mailer][Messenger][Notifier][Serializer][Translation][Validator][Workflow] Load profiler collectors regardless of kernel.debug (prateekbhujel)
 * bug #68431 [HttpClient] Don't cache responses canceled before their body was received (Tiriel, nicolas-grekas)
 * minor #68470 [HttpClient][RateLimiter] Don't run `RemoveMissingDependenciesPass` twice in the bundle tests (nicolas-grekas)

### HttpFoundation

 * bug #68472 [HttpFoundation] Match query parameter names regardless of order (malekkadri)
 * bug #68453 [HttpFoundation] Reject non-integer CIDR netmasks in IpUtils (bsulyan)
 * bug #68414 [HttpFoundation] Handle zero filter flags in InputBag and ParameterBag (malekkadri)
 * bug #68424 [HttpFoundation] Clear cookies when replacing headers in ResponseHeaderBag (bsulyan)

### HttpKernel

 * minor #68494 [HttpKernel] Fix transient failure of FragmentHandlerTest::testRender (clfer)
 * minor #68497 [HttpKernel] Move the cache and log dirs of KernelTest kernels out of the source tree (clfer)

### JsonSchema

 * bug #68462 [JsonSchema] Use the uri-reference format for protocol-relative URLs (Tong-bit-art)
 * bug #68430 [JsonSchema] Ignore normalized Email, Url, Ip and Uuid constraints (Tong-bit-art)
 * minor #68484 [JsonSchema] Fix JsonSchemaBundleTest when installed outside the monorepo (nicolas-grekas)

### Mailer

 * bug #68480 [Mailer][Mime] Encrypt each PGP recipient to the key from its own file (jack-worman)
 * bug #68471 [Mailer] Fix inline images referenced by Content-ID in the MailPace, Infobip and Mailomat transports (nicolas-grekas)
 * bug #68454 [Mailer][Mailgun] Fix rewriting cid references of inline parts with a Content-ID for Mailgun (jkabat)

### Messenger

 * bug #68479 [Messenger] Fix `--concurrency` stalls with batch handlers (nicolas-grekas)
 * bug #68459 [Messenger] Don't send flushes to a parallel worker that is inside a handler (mattleads)

### Notifier

 * minor #68451 [Notifier][Mercure] Add support for Mercure 0.10 (dunglas)
 * minor #68442 [Notifier][Bluesky] Remove duplicated `keywords` in the `composer.json` file (kevinonthebit)

### PropertyAccess

 * bug #68458 [PropertyAccess] Preserve escaped elements when resolving parent paths (malekkadri)

### Security

 * feature #68467 [Security] Keep SymfonyCasts as backers of the Security components v8.2 🤗 (nicolas-grekas)
 * bug #68492 [Security][SecurityBundle] Check the binding and the size of a DPoP proof key before verifying its signature (jack-worman)

### Serializer

 * bug #68473 [Serializer] Fix denormalizing XML and CSV numbers into `int|float` and `int` properties (nicolas-grekas)
 * bug #68448 [Serializer] Keep JSON integers as int when the union also accepts int (cestercian)
 * bug #68428 [Serializer] Read the current value with deep_object_to_populate when there is no property type extractor (dhrupo)
 * bug #68426 [Serializer] Fix denormalizing inherited private properties (Tong-bit-art)
 * bug #68427 [Serializer] Validate target type before data in NumberNormalizer::denormalize() (bsulyan)

### Stopwatch

 * bug #68450 [Stopwatch] Preserve explicit zero section origin (malekkadri)

### String

 * bug #68432 [String] Fix quadratic running time in snake() on long uppercase runs (irfanm96)

### WebProfilerBundle

 * bug #68489 [WebProfilerBundle] Recognize button.toggle-button as toggleControl without open/close icons (prateekbhujel)

### Yaml

 * bug #68445 [Yaml] Dump floats without losing precision (bardrop)

### Other

 * feature #68466 Shopware is backing Symfony 8.2, thanks to them! (nicolas-grekas)
 * feature #68465 Mailtrap is backing Symfony 8.2 and the Mailer component, thanks to them! (nicolas-grekas)
 * feature #68464 TYPO3 is backing Symfony 8.2, thanks to them! (nicolas-grekas)

## 8.2.0-RC1 (2026-11-25)

### Asset

 * bug #68401 [Asset] Preserve zero-valued paths in asset URLs (shuvroroy)

### AssetMapper

 * bug #68281 [AssetMapper] Fix detecting imports in JavaScript files (nicolas-grekas)
 * bug #68204 [AssetMapper][Messenger][Translation][TwigBridge] Escape values in text output and write JSON raw in commands (prateekbhujel)

### Cache

 * bug #68361 [Cache] Add support for relay 0.62 (nicolas-grekas)
 * minor #68253 [Cache] Use findOne and unordered bulk writes in the MongoDB adapters (GromNaN)
 * minor #68223 [Cache] Fix dead phpredis and predis links in comments (farisbasha)

### Config

 * bug #68300 [Config] Reject empty lists for nonempty maps in generated schemas (shuvroroy)
 * minor #68317 [Config] Fix ResourceCheckerConfigCacheTest::testAnUnchangedGeneratedFileNeedsOnlyOneCorrectiveBuild on slow runners (clfer)

### Console

 * bug #68398 [Console] Allow zero as table padding character (malekkadri)
 * bug #68382 [Console] Fix handling of zero option shortcuts (malekkadri)
 * bug #68376 [Console] Restore formatter decoration state on exceptions (malekkadri)
 * bug #68381 [Console] Retry alarms that interrupt an autoload instead of wrapping the autoloader (nicolas-grekas)
 * bug #68314 [Console] Fix "Class not found" when a signal handler needs a class being autoloaded (Inf1mum)
 * bug #68332 [Console] Fix how date arguments and options are resolved (nicolas-grekas)
 * bug #68284 [Console] Fix `#[AskChoice]` on `#[MapInput]` properties (nicolas-grekas)
 * bug #68283 [Console] Fix `#[Ask]` on `#[MapInput]` properties and out-of-range `bool|int` options (nicolas-grekas)
 * bug #68282 [Console] Fix validation of int-backed enums and numeric union options in invokable commands (prateekbhujel)
 * bug #68265 [Console] Fix the symfony/polyfill-php85 requirement and add tests for CombinedOutput (pawell67)
 * bug #68258 [Console] Fix default values of promoted properties in `#[MapInput]` DTOs (pawell67)
 * bug #68234 [Console] Stop rendering `PHP_BINARY` in `%command.invocation%` (nicolas-grekas)
 * bug #68237 [Console] Fix hydrating readonly properties of `#[MapInput]` DTOs (nicolas-grekas)
 * bug #68216 [Console] Fix `InputFile` with `file://` URIs that contain `%5C` or a host name (nicolas-grekas)
 * bug #68188 [Console][Translation][TwigBridge][Yaml] Escape file names and messages in the output of the lint commands (nicolas-grekas)
 * bug #68189 [Console][HttpKernel] Escape the output of `lint:expressions` and write the GitLab report raw (nicolas-grekas)
 * bug #68179 [Console] Fix file:// URI handling in InputFile (Tong-bit-art)
 * minor #68342 [Console] Fix the `tty` group of `TerminalInputHelperTest` (nicolas-grekas)

### DebugBundle

 * bug #68344 [DebugBundle] Register the VAR_DUMPER_SERVER default before resolving dump_destination (lazerg)

### DependencyInjection

 * bug #68399 [DependencyInjection] Handle invalid integer-backed enum env values (malekkadri)
 * bug #68392 [DependencyInjection] Preserve falsy query strings in env processor (malekkadri)
 * bug #68380 [DependencyInjection] Reset non-empty parameter metadata when clearing ParameterBag (malekkadri)
 * bug #68379 [DependencyInjection] Preserve trailing newlines when resolving parameters (malekkadri)
 * bug #68277 [DependencyInjection] Fix checking the type of env placeholders coming from extension configs (pawell67)
 * bug #68236 [DependencyInjection] Make `ResolveNoPreloadPass` iterative (nikophil)
 * bug #68212 [DependencyInjection] Don't keep the container in `AttributeAutoconfigurationPass` after processing (nicolas-grekas)
 * minor #68213 [DependencyInjection] Clear the service reference graph built by `InlineServiceDefinitionsPass` (nicolas-grekas)
 * minor #68209 [DependencyInjection] Release the container builder before loading the compiled container (vencakrecl)

### DoctrineBridge

 * security #cve-2026-133759 [DoctrineBridge] Refuse the copies of previous remember-me tokens as series, and delete them with their token (nicolas-grekas)
 * bug #68295 [DoctrineBridge] Filter invalid GUID values only on platforms with a native GUID type (prateekbhujel)
 * bug #68233 [DoctrineBridge] Undeprecate `DoctrinePingConnectionMiddleware`, it resets closed entity managers (nicolas-grekas)

### ExpressionLanguage

 * security #cve-2026-134791 [ExpressionLanguage] Lex comments in linear time (nicolas-grekas)
 * security #cve-2026-133760 [ExpressionLanguage] Check the nesting level of short ternaries in linear time (nicolas-grekas)

### Filesystem

 * bug #68394 [Filesystem] Handle scheme-only paths in Path::getRoot() (malekkadri)
 * bug #68374 [Filesystem] Fix Path::changeExtension() for zero extensions (malekkadri)

### Finder

 * bug #68385 [Finder] Fix case-insensitive date comparison operators (malekkadri)

### Form

 * bug #68405 [Form] Preserve four-digit years when transforming ISO weeks (shuvroroy)
 * bug #68404 [Form] Reject zero-valued date fields in DateTimeToArrayTransformer (malekkadri)
 * bug #68372 [Form][Security] Fix inaccurate Arabic translations (malekkadri)
 * bug #68347 [Form] Skip write-only virtual promoted properties in ConstructorMapper (prateekbhujel)
 * bug #68288 [Form] Fix losing digits when rounding big numbers (nicolas-grekas)
 * bug #68286 [Form] Accept integer limits in localized number transformers (ahbaqdadi)
 * bug #68276 [Form][HttpKernel][Intl][JsonStreamer][Serializer] Fix ValueError on date and timezone strings that contain a null byte (nicolas-grekas)
 * bug #68231 [Form][FrameworkBundle][Messenger] Escape user data and write raw dumps in console commands (prateekbhujel)
 * bug #68218 [Form] Don't submit a form with an empty name when the request contains none of its fields (nicolas-grekas)
 * bug #68215 [Form] Resolve the false value of missing checkboxes in Form::submit() (alexandre-castelain)
 * minor #68412 [Form][Security][Validator] Add Tajik (tg) translations (ismoil77)
 * minor #68407 [Form] Update Tagalog (tl) validator translations (edmer664)
 * minor #68329 [Form][Security][Translation][Validator] Move the mixed-alphabet check to translation-status.php (nicolas-grekas)
 * minor #68296 [Form][Security][Validator] Fix letters from another alphabet in el, mn and uk translations (bardrop, nicolas-grekas)
 * minor #68319 [Form][Validator] Refine Czech validation messages (hrabosh)
 * minor #68289 [Form][Validator] Review Hebrew (he) translations (oriko9)
 * minor #68269 [Form][Security][Validator] Review Basque (eu) translations (ikerib)
 * minor #68222 [Form][Security][Validator] Review Portuguese (pt) translations (TiagoCruz1224)
 * minor #68151 [Form][Validator] Review Uzbek (uz) translations (turgunovabb)
 * minor #68186 [Form][HttpFoundation][Messenger] Improve static types (HypeMC)

### FrameworkBundle

 * security #cve-2026-133762 [FrameworkBundle] Use the CLI error renderer only in debug mode (nicolas-grekas)
 * security #cve-2026-133747 [FrameworkBundle] Keep the options of scoped HTTP clients in their scope when caching is enabled (nicolas-grekas)
 * security #cve-2026-133745 [FrameworkBundle][Routing] Don't let router redirects send paths that start with // to another host (nicolas-grekas)
 * bug #68310 [FrameworkBundle] Fix `debug:config` when booting the kernel copy rebuilds the container (haraldpdl)
 * bug #68243 [FrameworkBundle][TwigBridge] Fix the JSON and YAML output of `debug:config <name> <path>` and escape values in `debug:twig` (nicolas-grekas)
 * bug #68162 [FrameworkBundle] Use the share directory for file sessions (nicolas-grekas)

### GHA

 * minor #68270 [GHA] Disable opcache in the CLI of the Windows all-extensions job (nicolas-grekas)

### HtmlSanitizer

 * security #cve-2026-133756 [HtmlSanitizer] Strip the whitespace that browsers skip before the time of a meta refresh (nicolas-grekas)

### HttpClient

 * security #cve-2026-133750 [HttpClient] Take the options set with withOptions() into account in CachingHttpClient (nicolas-grekas)
 * security #cve-2026-134792 [HttpClient] Read HTTP_PROXY from the environment, not from $_SERVER, on the CLI (nicolas-grekas)
 * security #cve-2026-133758 [HttpClient] Let the client decorated by NoPrivateNetworkHttpClient apply its own default options (nicolas-grekas)
 * security #cve-2026-133757 [HttpClient] Don't let decorated clients add their default credentials to cross-origin redirects of NoPrivateNetworkHttpClient (nicolas-grekas)
 * bug #68413 [HttpClient] Register HttpClientPass at default priority (nicolas-grekas)
 * bug #68406 [HttpClient] Set redirect_url on mocked redirect responses (nicolas-grekas)
 * bug #68356 [HttpClient] Fix CachingHttpClient letting cached chunks expire before their metadata (nicolas-grekas)
 * bug #68354 [HttpClient] Treat incomplete cached responses as cache misses (andreybolonin)
 * bug #68248 [HttpClient] Don't send the fragment of URLs to proxies with NativeHttpClient (nicolas-grekas)
 * bug #68198 [HttpClient] Fix memory leak in AmpHttpClient when responses are canceled (nicolas-grekas)
 * minor #68318 [HttpClient] Mark the pause-during-body test of AmpHttpClient as transient on Windows (clfer)
 * minor #68316 [HttpClient] Skip CurlHttpClientTest::testTimeoutOnDestruct on Windows (clfer)
 * minor #68245 [HttpClient] Fix a max_duration test that fails on slow machines (nicolas-grekas)
 * minor #68202 [HttpClient] Pause the max-duration test well past its limit, so that a slow machine is not taken for a wait on the pause (phcorp)
 * minor #68159 [HttpClient] Let the test suite run on another port so that several runs can work in parallel (nicolas-grekas)

### HttpFoundation

 * security #cve-2026-133748 [HttpFoundation] Match IPv4-mapped addresses against IPv4 subnets in IpUtils::checkIp() (nicolas-grekas)
 * security #cve-2026-133743 [HttpFoundation][HttpKernel] Trust 127.0.0.1 only for the requests built by SubRequestHandler (nicolas-grekas)
 * bug #68388 [HttpFoundation] Reset cache-control directives when replacing headers (malekkadri)
 * bug #68373 [HttpFoundation] Fix AcceptHeaderItem parsing of empty input (malekkadri)
 * bug #68371 [HttpFoundation] Fix Request::getRequestUri() for an absolute URL without a path (lazerg)
 * bug #68290 [HttpFoundation] Anonymize bracketed scoped IPv6 addresses (ahbaqdadi)
 * bug #68287 [HttpFoundation] Fix UriSigner dropping the "@" when the user is "0" (nicolas-grekas)
 * bug #68285 [HttpFoundation] Preserve network-path URLs when signing URIs (ahbaqdadi)
 * minor #68227 [HttpFoundation] Fix the dead OWASP cheat sheet link in JsonResponse (farisbasha)

### HttpKernel

 * bug #68262 [HttpKernel] Fix `#[MapQueryParameter]` throwing on an invalid Uid (anthonyhacart)
 * bug #68165 [HttpKernel] Limit IPv6 clients by their /64 network in the default #[RateLimit] key (jack-worman)
 * bug #68161 [HttpKernel] Accept JSON and XML media type variants in `#[MapRequestPayload]` (nicolas-grekas)
 * minor #68321 [HttpKernel] Fix transient failure of InlineFragmentRendererTest (clfer)
 * minor #68249 [HttpKernel] Fix transient failures in ResponseCacheStrategyTest (nicolas-grekas)
 * minor #68244 [HttpKernel][SecurityBundle] Make time-dependent tests independent of second boundaries (nicolas-grekas)

### JsonPath

 * security #cve-2026-133744 [JsonPath] Bound the work and the length of queries (nicolas-grekas)
 * bug #68268 [JsonPath] Fix comparisons and function arguments to follow RFC 9535 (nicolas-grekas)
 * bug #68267 [JsonPath] Fix comparing absolute queries that select objects in filters (pawell67)
 * bug #68266 [JsonPath] Don't consider null and absent values as equal to 0 (pawell67)

### JsonSchema

 * bug #68327 [JsonSchema] Align the keywords of Length, Regex, NotBlank and repeated constraints with the validator (nicolas-grekas)
 * bug #68301 [JsonSchema] Emit length bounds only for code point counts (shuvroroy)
 * bug #68328 [JsonSchema] Handle Valid and nested composites inside Sequentially and Compound constraints (prateekbhujel)
 * bug #68297 [JsonSchema] Do not allow null for NotBlank and NotNull (Tong-bit-art)

### JsonStreamer

 * bug #68352 [JsonStreamer][PropertyAccess] Fix reading and writing virtual hooked properties (nicolas-grekas)
 * bug #68343 [JsonStreamer] Write dicts of scalars as JSON objects (nicolas-grekas)
 * bug #68336 [JsonStreamer] Do not use json_encode() for non-array iterables (prateekbhujel)
 * bug #68335 [JsonStreamer] Fix encoding numeric string keys in dict collections (prateekbhujel)

### KeyManagement

 * bug #68337 [KeyManagement] Return string from EncryptedType::convertToDatabaseValue() (prateekbhujel)
 * bug #68302 [KeyManagement] Reuse factories supplied by a generator (shuvroroy)
 * minor #68246 [KeyManagement] Mention self-hosted production in the SodiumKms docblock (nikophil)
 * minor #66273 [KeyManagement] Register encrypted DBAL types on the connection, not the static registry (GromNaN, nicolas-grekas)

### Lock

 * bug #68333 [Lock] Wrap store exceptions thrown by `exists()` in `Lock::release()` (dmitryuk)
 * bug #68197 [Lock] Fix the Oracle clock of DoctrineDbalStore and PdoStore counting the time of day twice (nicolas-grekas)
 * bug #68174 [Lock] Fix the MySQL clock of DoctrineDbalStore and PdoStore jumping an hour when DST ends (jack-worman)

### Mailer

 * security #cve-2026-133755 [Mailer] Refuse to start TLS when the server sends data before the handshake (nicolas-grekas)
 * security #cve-2026-133751 [Mailer] Make `require_tls` resist a man in the middle (nicolas-grekas)
 * minor #68203 [Mailer] Give gpg more time to start in the test of PgpMimeEncryptedMessageListener on Windows CI (phcorp)

### Messenger

 * bug #68411 [Messenger][Scheduler] Register MessengerPass at default priority (prateekbhujel)
 * bug #68393 [Messenger] Preserve millisecond precision in DelayStamp::delayFor() and delayUntil() (malekkadri)
 * bug #68313 [Messenger] Fix --concurrency pausing consumption while probing a message key (Inf1mum)
 * bug #68224 [Messenger] Reset Redis receiver state when closing (dalerazimov)
 * bug #68205 [Messenger] Keep plus signs in the vhost of AMQP DSNs (nicolas-grekas)
 * bug #68200 [Messenger] Make `messenger:stop-workers` fail when the stop signal can't be saved (javiereguiluz)
 * minor #68320 [Messenger] Skip transient AmpSql transport tests on Windows (clfer)

### Mime

 * bug #68403 [Mime] Preserve zero-valued names when building MIME messages (malekkadri)
 * bug #68384 [Mime] Preserve zero-valued stream bodies in TextPart (malekkadri)
 * bug #68206 [Mime] Throw a Mime exception when gpg times out (nicolas-grekas)
 * minor #68325 [Mime] Fix AddressTest with egulias/email-validator 4.0.5 (phcorp)

### Notifier

 * bug #68345 [Notifier][SmsProxima] Fix the "default" host in DSN (SMS-Proxima)
 * bug #68326 [Notifier][Bluesky] Fix overlapping facets and mentions detected inside email addresses (nicolas-grekas)
 * bug #68324 [Notifier][Bluesky] Fix mentions and links at the start of the text not being detected (thejesh23)
 * minor #68220 [Notifier] Update dead Engagespot and Plivo documentation links (farisbasha)

### ObjectMapper

 * bug #68341 [ObjectMapper] Fix mapping write-only virtual properties and nested targets that extend internal classes (nicolas-grekas)
 * bug #68338 [ObjectMapper] Fix reading virtual hooked properties and properties of ArrayObject subclasses (prateekbhujel)

### PhpUnitBridge

 * minor #68211 [PhpUnitBridge] Limit how many suites run at once on the Windows CI (nicolas-grekas)

### Process

 * bug #68306 [Process] Never generate an all-digit env var name on Windows (linzch3)

### PropertyAccess

 * bug #68340 [PropertyAccess][PropertyInfo] Handle write-only virtual hooked properties (prateekbhujel)
 * bug #68278 [PropertyAccess] Fix double periods in the messages of exceptions about non-writable properties (pawell67)
 * bug #68264 [PropertyAccess] Throw NoSuchPropertyException when writing a property that is not publicly writable (pawell67)

### PropertyInfo

 * bug #68353 [PropertyInfo] Fix `PhpDocExtractor` reading `iterable<K, V>` and pseudo types as class names (nicolas-grekas)
 * bug #68294 [PropertyInfo] Limit the caches that grow with the input (nicolas-grekas)
 * bug #68261 [PropertyInfo] Fix getWriteInfo() returning the adder/remover for a singular property (cestercian)
 * bug #68272 [PropertyInfo] Fix `getReadInfo()` for non-public properties of classes that define `__get()` (nicolas-grekas)
 * bug #68271 [PropertyInfo] Fix `getWriteInfo()` for non-public properties of classes that define `__set()` (nicolas-grekas)
 * minor #68207 [PropertyInfo] Use a PHPDoc type that TypeInfo can't resolve in the test of inherited types (nicolas-grekas)

### PsrHttpMessageBridge

 * bug #68364 [PsrHttpMessageBridge] Don't read the stream of an uploaded file that has an upload error (vasikmansuri)

### Routing

 * security #cve-2026-133754 [Routing] Reject host parameters that contain forbidden characters (nicolas-grekas)
 * security #cve-2026-133746 [Routing] Don't let generated URLs point to another host (nicolas-grekas)
 * bug #68396 [Routing] Compare alias names strictly when detecting circular references (malekkadri)
 * bug #68383 [Routing] Preserve explicit ports for case-insensitive URI schemes (malekkadri)

### Scheduler

 * bug #68323 [Scheduler] Fix the "nth weekday" cron syntax being treated as a hashed expression (thejesh23)

### Security

 * security #cve-2026-134790 [Security] Parse the host of redirect targets like browsers do (nicolas-grekas)
 * security #cve-2026-133753 [Security] Require every attribute to be granted with the unanimous strategy (nicolas-grekas)
 * security #cve-2026-133749 [Security] Run the checkPostAuth() method of user checkers in GuardAuthenticatorHandler::authenticateUserAndHandleSuccess() (nicolas-grekas)
 * bug #68359 [Security][Validator] Review Brazilian Portuguese (pt_BR) translations (andretorresdg)
 * bug #68187 [Security] Fix login throttling of requests that have no client IP (nicolas-grekas)

### SecurityBundle

 * bug #68369 [SecurityBundle] Fix the Token tab of the profiler being disabled after a deauthentication (javiereguiluz)
 * bug #68303 [SecurityBundle] Show shared roles under each parent in the debug tree (shuvroroy)

### Serializer

 * security #cve-2026-133761 [Serializer] Limit the nesting depth of CSV headers (jack-worman)
 * security #cve-2026-133752 [Serializer] Compute the allowed attributes for the class being denormalized (nicolas-grekas)
 * bug #68389 [Serializer] Handle invalid input types in DateTimeZoneNormalizer (malekkadri)
 * bug #68391 [Serializer] Fix empty CSV line endings (malekkadri)
 * bug #68351 [Serializer] Handle mixed/template array keys and DNF/template types in denormalization (prateekbhujel)
 * bug #68346 [Serializer] Fix XmlEncoder::FORCE_COLLECTION and match repeated element names (prateekbhujel)
 * bug #68349 [Serializer] Fix PropertyNormalizer with virtual hooked properties and inherited private(set) (prateekbhujel)
 * bug #68348 [Serializer] Respect COLLECT_DENORMALIZATION_ERRORS => false in Serializer::denormalize() (prateekbhujel)
 * bug #68293 [Serializer] Limit the caches that grow with each nesting path (nicolas-grekas)
 * bug #68292 [Serializer] Don't ask PropertyInfo about each case variant of an attribute name (nicolas-grekas)
 * minor #68180 [Serializer] Reset the attributes cache of object normalizers (soyuka)
 * minor #68196 [Serializer] Resolve the type of each constructor parameter once (christian-kolb)
 * minor #68195 [Serializer] Create the property accessors used for serialized paths once per normalizer (christian-kolb)

### Stopwatch

 * bug #68378 [Stopwatch] Fix incorrect start time for nested event periods (malekkadri)

### String

 * bug #68395 [String] Fix source encoding detection in toByteString() (malekkadri)
 * bug #68375 [String] Fix negative offsets when searching for multiple needles (malekkadri)

### Translation

 * bug #68402 [Translation] Keep "0" message IDs and translations in gettext loaders (malekkadri)
 * bug #68397 [Translation] Handle malformed INI translation resources (malekkadri)
 * bug #68390 [Translation] Handle invalid JSON translation resource types (malekkadri)
 * bug #68387 [Translation] Fix handling of zero catalogue metadata keys and domains (malekkadri)
 * minor #68304 [Translation] Check XLIFF path selection through the updated files (shuvroroy)

### Tui

 * bug #68256 [Tui] Don't cut a select list label that fits (phcorp)
 * bug #68254 [Tui] Show the minimum lines of an editor past its content (phcorp)
 * bug #68255 [Tui] Name the argument of EditorWidget::expandVertically() like its interface (phcorp)
 * bug #68247 [Tui] Turn the background off at the end of a wrapped line (phcorp)
 * bug #68242 [Tui] Turn strikethrough and inverse off at the end of a wrapped line (phcorp)
 * bug #68241 [Tui] Hide HTML comments in Markdown (phcorp)
 * bug #68240 [Tui] Recognize unmodified F1, F2 and F4 sent as CSI P, Q and S (phcorp)
 * bug #68239 [Tui] Restore the terminal when the process exits without stop() (phcorp)
 * bug #68185 [Tui] Let a TUI dropped without being stopped be garbage collected (phcorp)

### TwigBundle

 * feature #67135 [TwigBundle] Register subclasses of AttributeExtension as runtimes (GromNaN)

### TypeInfo

 * bug #68199 [TypeInfo] Resolve conditional PHPDoc return types (drewmt)

### Validator

 * bug #68386 [Validator] Fix the error of Count when its max is zero (nicolas-grekas)
 * bug #68377 [Validator] Fix incorrect validation error for zero maximum length (malekkadri)
 * bug #68360 [Validator] Fix the Portuguese (pt) translation of the Currency constraint message (andretorresdg)
 * bug #68350 [Validator] Skip write-only virtual properties in Cascade and PropertyInfoLoader (prateekbhujel)
 * bug #68274 [Validator] Fix DateTime constraint throwing ValueError on values containing a null byte (pcbeingused333)
 * minor #68226 [Validator] Fix the dead Have I Been Pwned API link (farisbasha)

### Yaml

 * bug #68280 [Yaml] Fix how `lint:yaml` reads the `$schema` header and follows `$ref` (nicolas-grekas)
 * bug #68279 [Yaml] Decode percent-encoded schema header paths (Tong-bit-art)
 * bug #68210 [Yaml] Escape the schema path in the text output of lint:yaml (nicolas-grekas)

### Other

 * minor #68250 Limit how many suites run at once on the Windows CI of 5.4 (nicolas-grekas)
 * minor #68238 Download CI binaries from symfony-tools/binary-utils (nicolas-grekas)
 * minor #68229 Remove the security-triage skill (nicolas-grekas)
 * minor #68164 Declare bundle options that resolve at compile time (nicolas-grekas)

## 8.2.0-BETA2 (2026-11-13)

### AssetMapper

 * bug #68081 [AssetMapper] Fix comparing 0.x and pre-release versions in `importmap:outdated` and `importmap:audit` (nicolas-grekas)
 * bug #68076 [AssetMapper] Compare pre-releases in importmap:outdated (Amoifr)
 * bug #68023 [AssetMapper] Resolve the env vars of `asset_mapper.server` when the container is compiled (lazerg)
 * minor #68056 [AssetMapper] Backdate the meta file too in `CachedMappedAssetFactoryTest` (nicolas-grekas)

### BrowserKit

 * bug #68061 [BrowserKit][HttpClient] Don't send credentials to other origins on redirects (nicolas-grekas)

### Cache

 * bug #68129 [Cache][FrameworkBundle] Fix async recomputation for chains and decorated pools (X-Coder264)
 * bug #68123 [Cache] Check cache keys even when assertions are disabled (nicolas-grekas)
 * bug #68091 [Cache] Fix ChainAdapter losing the expiry of items propagated from FilesystemAdapter (skybleak333)
 * bug #68090 [Cache] Fix items without expiry read back as expiring in 2090 (skybleak333)
 * bug #68113 [Cache][Messenger][Notifier] Fix AMQP, Memcached, Ntfy and Spot-Hit misreading DSN option values (nicolas-grekas)
 * bug #68047 [Cache][Messenger] Fix Redis connections and the Beanstalkd transport reading invalid option values as 0 or false (nicolas-grekas)
 * bug #68039 [Cache][Messenger] Fix Redis connections and Messenger transports misreading integer and boolean options (nicolas-grekas)
 * bug #68038 [Cache][HttpFoundation][Lock] Fix memcached items with a TTL ending after 2038 expiring at once (nicolas-grekas)
 * bug #67924 [Cache][FrameworkBundle][Routing][Serializer][Translation][TwigBundle][Validator] Preserve legacy types of moved services (X-Coder264)
 * bug #67850 [Cache] Declare the temporary tag set as KEYS[2] in RedisTagAwareAdapter::doInvalidate() (srebb)
 * bug #67864 [Cache] Fix fetching long keys with MemcachedAdapter::getItems() (lazerg)

### CI

 * minor #67930 [CI] Add the repository of patched packages to components that declare their own repositories (nicolas-grekas)

### Config

 * bug #67960 [Config] Compare resource freshness with cache build start (X-Coder264)
 * bug #67977 [Config][Routing] Fix excluded paths in XML route imports and in `GlobResource` ids (nicolas-grekas)
 * bug #67968 [Config] Compare the content of directory entries modified in the same second as the cache (nicolas-grekas)
 * bug #67952 [Config] Fix DirectoryResource checking the parent directory on Windows (nicolas-grekas)
 * bug #67927 [Config] Fix EnumNode error messages for INF, NAN and enum cases without a backing value (nicolas-grekas)
 * bug #67911 [Config] Do not treat NAN as a placeholder (haraldpdl)
 * minor #68033 [Config] Tell in config references when env vars are resolved at compile time (nicolas-grekas)

### Console

 * bug #67934 [Console] Fix formatting and wrapping text with escaped tag delimiters (nicolas-grekas)
 * bug #67903 [Console] Fix wrapping escaped tags and text wider than 138 columns (nicolas-grekas)
 * bug #67897 [Console] Fix the width of tags and URLs when wrapping text (nicolas-grekas)
 * bug #67890 [Console] Count wide characters as two columns when wrapping and truncating (b84330808)

### DependencyInjection

 * bug #67976 [DependencyInjection][Routing] Track the glob resource of `import()` so new files are detected (abp002)
 * bug #67975 [DependencyInjection][Routing][Translation] Track loaded files before reading them (nicolas-grekas)
 * bug #67970 [DependencyInjection] Fix `parent::buildContainer()` tracking the default `bundles.php` of a `MicroKernelTrait` kernel (nicolas-grekas)
 * bug #67938 [DependencyInjection][HttpKernel] Fix type-hinting `file_locator` and `services_resetter` with their deprecated HttpKernel types (nicolas-grekas)
 * bug #67861 [DependencyInjection] Fix resolving env placeholders with many env vars (simoheinonen)
 * minor #68137 [DependencyInjection] Fix attribute configurator phpdoc rejecting narrowed reflectors (HypeMC)

### DoctrineBridge

 * bug #68142 [DoctrineBridge] Fix UniqueEntity default error path when fields is a mapping (lazerg)
 * minor #68104 [DoctrineBridge] Use the schema editor API of Doctrine ORM in tests (nicolas-grekas)

### Finder

 * bug #67926 [Finder] Fix Glob::toRegex() when the delimiter is a slash (nicolas-grekas)
 * bug #67915 [Finder] Fix three bracket expressions Glob::toRegex() gets wrong (KalimeroMK)

### Form

 * bug #67962 [Form] Review Macedonian (mk) translations (KalimeroMK)
 * bug #67865 [Form] Let a validation_groups callable return false to disable validation (KalimeroMK)
 * minor #68071 [Form][Validator] Review Hungarian translations (connorhu)
 * minor #67989 [Form][Validator] Review Catalan (ca) translations (Victor-369)
 * minor #67866 [Form] Review Bulgarian (bg) translations (alkinbg)

### FrameworkBundle

 * bug #68112 [FrameworkBundle] Give the test session listener the same services as the real one (nicolas-grekas)
 * bug #68065 [FrameworkBundle][HttpClient] Match scopes against the URL without its userinfo (nicolas-grekas)
 * bug #67891 [FrameworkBundle] Lint and compile the `$if` expressions of `#[RateLimit]` (HypeMC)
 * minor #67971 [FrameworkBundle] Restore the `APP_*_DIR` server variables set by `MicroKernelTraitTest` (nicolas-grekas)
 * minor #67899 [FrameworkBundle] Check that controller.expression_language lists every Expression property of the HttpKernel attributes (nicolas-grekas)

### HtmlSanitizer

 * bug #67886 [HtmlSanitizer] Don't treat elements and attributes that act on the page as safe (nicolas-grekas)
 * bug #67885 [HtmlSanitizer] Block `html` and `body` in `allowSafeElements()` and don't let links expose `window.opener` (nicolas-grekas)
 * bug #67879 [HtmlSanitizer] Don't treat `link`, `meta`, `plaintext` and `referrerpolicy` as safe (nicolas-grekas)

### HttpClient

 * bug #68130 [HttpClient] Don't send an event source request again when its method is not idempotent (skybleak333)
 * bug #68131 [HttpClient] Fix parsing an SSE event with an empty type or an empty first data line (skybleak333)
 * bug #68128 [HttpClient] Fix CurlHttpClient resending request bodies on redirects (nicolas-grekas)
 * bug #68125 [HttpClient] Read Retry-After dates as HTTP-dates in RetryableHttpClient (nicolas-grekas)
 * bug #68094 [HttpClient] Fix taking invalid HTTP dates for valid ones (skybleak333)
 * bug #68120 [HttpClient] Don't report the user info of URLs (nicolas-grekas)
 * bug #68109 [HttpClient] Fix CachingHttpClient failing to stream `no-store` requests together with other requests (nicolas-grekas)
 * bug #68114 [HttpClient] Fix AmpHttpClient sending `Content-Length: 0` for pipe bodies (nicolas-grekas)
 * bug #68095 [HttpClient] Fix sending an empty body when a stream body is sent again (skybleak333, nicolas-grekas)
 * bug #68093 [HttpClient] Fix CachingHttpClient::stream() yielding a stream instead of its chunks (skybleak333)
 * bug #67925 [HttpClient] Keep the response decorated by TraceableResponse usable until it is freed (lazerg)
 * bug #67922 [HttpClient] Fix the allow list of NoPrivateNetworkHttpClient letting its whole address family through (nicolas-grekas)
 * bug #67859 [HttpClient] Reject `CURLOPT_COOKIE` and `CURLOPT_UNRESTRICTED_AUTH` in `extra.curl` (nicolas-grekas)
 * bug #67851 [HttpClient] Fix an inverted check in `AmpResolverV5::query()` (nicolas-grekas)
 * bug #67842 [HttpClient][Validator] Add the deprecated IPv6 site-local range to the private subnets (nicolas-grekas)
 * bug #67849 [HttpClient] Fix a typo in NoPrivateNetworkHttpClient (Jontsa)
 * bug #67858 [HttpClient] Use the first `Location` header in CurlHttpClient, like curl does (nicolas-grekas)
 * minor #68102 [HttpClient] Document that the proxy must block private destinations behind NoPrivateNetworkHttpClient (nicolas-grekas)
 * minor #68119 [HttpClient] Fix testing redirects with user info on libcurl < 8.23 (nicolas-grekas)
 * minor #67870 [HttpClient] Conflict with amphp/amp < 3.0.1, whose weakClosure() breaks on PHP 8.4 (phcorp)

### HttpFoundation

 * bug #68121 [HttpFoundation] Parse HTTP dates as RFC 9110 says (nicolas-grekas)
 * bug #68106 [HttpFoundation][RateLimiter] Fix combined limiters reporting a reset time before all their limiters are back to full capacity (nicolas-grekas)
 * bug #67921 [HttpFoundation] Check IPv4-mapped, 6to4, Teredo and NAT64 addresses as their IPv4 address in IpUtils::isPrivateIp() (nicolas-grekas)
 * bug #67874 [HttpFoundation] Cap the size of the cache of IpUtils (nicolas-grekas)

### HttpKernel

 * bug #67985 [HttpKernel] Normalize percent-encoding in the default #[RateLimit] key (jack-worman)
 * bug #67966 [HttpKernel] Fix the bundle cache dumped by `parent::dumpContainer()` in a `MicroKernelTrait` kernel (lazerg)
 * bug #67950 [HttpKernel] Fix HttpCache serving must-revalidate responses stale while another process revalidates them (nicolas-grekas)
 * bug #67949 [HttpKernel] Fix the HttpCache lock being released when another process stores the same URL (nicolas-grekas)
 * bug #67946 [HttpKernel] Fix HttpCache serving a 503 after validating a no-cache entry when the cache is locked (nicolas-grekas)
 * bug #67944 [HttpKernel] Release the cache lock when a revalidated response is not stored (KalimeroMK)
 * bug #67847 [HttpKernel][Security] Split methods on commas in `#[IsGranted]`, `#[IsCsrfTokenValid]` and `#[IsSignatureValid]` (nicolas-grekas)
 * minor #68152 [HttpKernel] Warm property info for mapped controller arguments (nicolas-grekas)
 * minor #67892 [HttpKernel] Allow extending `#[RateLimit]` and `#[Lock]` attributes (HypeMC)

### JsonSchema

 * feature #67853 [JsonSchema] Preserve generic class arguments in schema definitions (X-Coder264)
 * bug #68135 [JsonSchema] Skip values that JSON cannot encode (skybleak333)
 * bug #68134 [JsonSchema] Use object keywords for Count, NotBlank and Choice on arrays described as objects (skybleak333)
 * bug #68133 [JsonSchema] Fix the schema of properties that have a serialized path (skybleak333)
 * bug #67857 [JsonSchema] Preserve serialized item types in list-shaped arrays (X-Coder264)
 * minor #67954 [JsonSchema] Cover untagged template inheritance with tests (X-Coder264)

### JsonStreamer

 * bug #68004 [JsonStreamer] Stop expanding generic types that get too long (nicolas-grekas)
 * bug #67933 [JsonStreamer][TypeInfo] Keep list shapes as lists (nicolas-grekas)
 * bug #67932 [JsonStreamer][TypeInfo] Fix resolving unions that repeat the same type (nicolas-grekas)
 * bug #67931 [JsonStreamer] Resolve the templates of nested generic types (nicolas-grekas)
 * bug #67901 [JsonStreamer] Resolve the templates of inherited properties when the streamed class is not generic (nicolas-grekas)
 * bug #67855 [JsonStreamer] Resolve the templates of inherited properties through ``@extends`` tags (nicolas-grekas)

### KeyManagement

 * minor #68108 [KeyManagement] Use the schema editor API of Doctrine ORM in tests (nicolas-grekas)

### Lock

 * bug #68041 [Lock] Fix MemcachedStore with keys that memcached rejects (nicolas-grekas)
 * bug #68037 [Lock][Semaphore] Fix RedisStore taking Predis error replies for a success (nicolas-grekas)
 * bug #68029 [Lock] Fix RedisStore taking Predis error replies for a success (skybleak333)
 * bug #68028 [Lock] Fix MemcachedStore locks with a TTL longer than 30 days expiring at once (skybleak333)
 * bug #68027 [Lock] Fix MongoDbStore::exists() failing before the store connected (skybleak333)
 * bug #67996 [Lock] Fix the PostgreSQL stores inheriting the locks of freed connections (nicolas-grekas)
 * bug #67994 [Lock] Fix DoctrineDbalPostgreSqlStore refusing locks after the session is lost (skybleak333)
 * minor #68080 [Lock] Freeze the clock in `KeyTest` (nicolas-grekas)
 * minor #68031 [Lock] Do not query the store when the key has no state (GromNaN)

### Mailer

 * bug #68111 [Mailer][Sweego] Do not send Bcc addresses as a header (nicolas-grekas)
 * bug #68100 [Mailer][AhaSend] Do not send Bcc addresses as a header (Sameer1122)
 * bug #68084 [Mailer][Postal] Keep the display names of the addresses (abp002)
 * bug #68063 [Mailer] Require TLS when more bridges connect with STARTTLS (nicolas-grekas)
 * bug #68062 [Mailer] Require TLS when bridges connect with STARTTLS (nicolas-grekas)
 * bug #67979 [Mailer][AhaSend] Take the Message-ID from message_id_header, without angle brackets (Sameer1122)
 * bug #68044 [Mailer][AhaSend] Use the Message-ID header as the id of messages sent over SMTP (nicolas-grekas)
 * bug #67978 [Mailer][AhaSend] Call api.ahasend.com for the API (Sameer1122)
 * bug #67980 [Mailer] Don't take a word of the SMTP reply as the message id (nicolas-grekas)
 * minor #68117 [Mailer][Notifier] Deprecate non-boolean `verify_peer` and `auto_tls` SMTP options, and validate the poll options of Instagram and Threads (nicolas-grekas)

### Messenger

 * bug #68110 [Messenger] Close AMQP connections when the transport closes (dalerazimov)
 * bug #68012 [Messenger] Close the `CloseableTransportInterface`s when the worker stops (wikando-ck)
 * bug #68045 [Messenger] Fix parallel workers crashing or hanging on errors in their child processes (nicolas-grekas)
 * bug #68043 [Messenger] Fix the Redis transport ignoring the `cluster` option (nicolas-grekas)
 * bug #67988 [Messenger] Fix parallel workers crashing on errors that cannot be serialized (lazerg)
 * bug #68022 [Messenger] Fix RedisReceiver losing the rest of a batch when rejecting an entry without data fails (skybleak333)
 * bug #68013 [Messenger] Fix LISTEN/NOTIFY with the DBAL pgsql driver (FluffyDiscord)
 * bug #68020 [Messenger] Fix the Redis transport losing pending messages after a failed read (skybleak333)
 * bug #67986 [Messenger] Reset services after a failed parallel dispatch (Stella050408)
 * bug #68017 [Messenger] Fix the Redis keepalive check comparing milliseconds with seconds (skybleak333)
 * bug #67993 [Messenger] Keep alive the fetched messages that wait for their turn (skybleak333)
 * bug #67987 [Messenger] Fix replacing a parallel worker that reached its memory limit (nicolas-grekas)
 * bug #67912 [Messenger] Stop the worker when a signal arrives before it is created (haraldpdl)

### Mime

 * bug #67871 [Mime] Fix SMimeSigner splitting a CRLF across chunk boundaries (w3dev-dk)

### Notifier

 * bug #68116 [Notifier] Fix the Ntfy and Spot-Hit transports misreading `secureHttp` and `smslongnbr` (nicolas-grekas)
 * bug #68105 [Notifier][Brevo] Use the non-deprecated transactionalSMS/send endpoint (amenophis)
 * bug #68103 [Notifier][MicrosoftTeams] Fix text-only messages sent to Workflows webhooks and document their setup (nicolas-grekas)

### ObjectMapper

 * bug #68118 [ObjectMapper] Fix unmapped source properties being read needlessly (GaryPEGEOT)
 * bug #67916 [ObjectMapper] Resolve self and parent property types when inferring nested mappings (nicolas-grekas)
 * bug #67889 [ObjectMapper] Fix mapping nested objects into properties typed self or parent (alexisLefebvre)

### PropertyAccess

 * bug #67902 [PropertyAccess] Limit the caches that grow with the input (nicolas-grekas)
 * minor #67937 [PropertyAccess][Serializer] Declare the return type of __set() in tests (nicolas-grekas)

### PropertyInfo

 * feature #68155 [PropertyInfo][Serializer][Validator] Warm classes from tagged providers (nicolas-grekas)
 * feature #67967 [PropertyInfo][TypeInfo] Inherit PHPDoc types from parent classes, interfaces and abstract trait methods (nicolas-grekas)
 * bug #68006 [PropertyInfo][TypeInfo] Don't throw on docblock types with invalid array keys (nicolas-grekas)
 * bug #67958 [PropertyInfo] Fix reading the PHPDoc of abstract trait methods after a docblock on the `use` statement (nicolas-grekas)
 * bug #67956 [PropertyInfo] Fix reading the PHPDoc of trait members and of inherited promoted properties (nicolas-grekas)
 * bug #67948 [PropertyInfo] Fix resolving the PHPDoc types of trait methods and of classes using traits (nicolas-grekas)
 * bug #67942 [PropertyInfo] Fix resolving the PHPDoc types of trait methods (nicolas-grekas)
 * bug #67943 [PropertyInfo] Preserve inherited templates in trait contexts (X-Coder264)
 * bug #67935 [PropertyInfo] Fix resolving `self`, `static`, `parent` and `$this` with `getType()` and `getTypeFromConstructor()` (nicolas-grekas)

### RateLimiter

 * bug #68088 [RateLimiter] Fix TokenBucketLimiter counting the reset time from now (skybleak333)
 * bug #68057 [RateLimiter] Fix `RateLimit::wait()` for delays over 35 minutes on 32-bit platforms (nicolas-grekas)
 * bug #68040 [RateLimiter] Fix CompoundLimiter telling to retry before all its limiters accept the hit again (nicolas-grekas)
 * bug #68021 [RateLimiter] Fix SlidingWindowLimiter letting reservations through faster than the limit (skybleak333)
 * bug #68019 [RateLimiter] Fix TokenBucketLimiter counting the wait for the next tokens from now (skybleak333)
 * bug #68016 [RateLimiter] Fix CompoundLimiter accepting a hit that one limiter refused (skybleak333)

### Routing

 * bug #68143 [Routing] Reject route parameters named or mapped to "_firewall" and "_firewall_context" (nicolas-grekas)
 * bug #68018 [Routing] Fix compiled matchers accepting extra trailing slashes on static routes (nicolas-grekas)
 * bug #68003 [Routing] Use the host value of variables that are both in the host and the path in compiled matchers (nicolas-grekas)
 * bug #68014 [Routing] Fix the order of routes with host variables in compiled matchers (nicolas-grekas)
 * bug #68002 [Routing] Make named groups in requirements non-capturing, like unnamed ones (nicolas-grekas)
 * bug #67947 [Routing][TwigBundle] Keep `routing.loader` a `DelegatingLoader` in apps that use TwigBundle (nicolas-grekas)
 * bug #67929 [Routing] Prevent `{_route}` host placeholders from matching the path in compiled matchers (nicolas-grekas)

### Runtime

 * bug #68107 [Runtime] Reset the memory peak between FrankenPHP worker requests (polem)

### Scheduler

 * bug #68089 [Scheduler] Fix a message added to a running schedule running all its earlier slots at once (skybleak333)
 * bug #68034 [Scheduler] Fix losing or repeating runs, and truncating float intervals (nicolas-grekas)
 * bug #68026 [Scheduler] Fix ISO 8601 intervals with days, weeks or months being counted as a fixed number of seconds (skybleak333)
 * bug #68025 [Scheduler] Fix cron triggers without a timezone taking the one of the last run after a restart (skybleak333)
 * bug #68030 [Scheduler] Fix the worker crashing when its lock expired during a long run (skybleak333)

### Security

 * bug #68144 [Security] Refuse DPoP-bound access tokens presented as bearer tokens (nicolas-grekas)
 * bug #68078 [Security][SecurityBundle] Fix profiles listing the access decisions of previous requests (nicolas-grekas)
 * bug #68008 [Security] Count login attempts before checking their credentials (nicolas-grekas)
 * bug #68010 [Security] Limit login attempts of IPv6 clients by their /64 network (nicolas-grekas)
 * bug #68007 [Security] Keep the IP limit of login throttling on successful logins (nicolas-grekas)
 * bug #67991 [Security] Remove accents and invisible characters from login throttling keys (nicolas-grekas)
 * bug #67845 [Security] Fix the login throttling message announcing one minute too many (lazerg)
 * bug #67875 [Security] Reject X509 DNs whose identifier attribute appears more than once (nicolas-grekas)
 * bug #67876 [Security] Clear the pre-authenticated token when REMOTE_USER is empty (nicolas-grekas)

### SecurityBundle

 * feature #67963 [SecurityBundle] Default the audience of the oidc and oauth2 token handlers to the resource of resource_metadata (KalimeroMK)
 * bug #68098 [SecurityBundle] Avoid unintended session usage with remember-me on stateless routes (isihussain)
 * bug #68075 [SecurityBundle] Put the listeners of a lazy firewall context back after tracing them (Amoifr)

### Semaphore

 * bug #68042 [Semaphore] Fix RedisStore expiring semaphores too early (nicolas-grekas)
 * bug #68011 [Semaphore] Fix LockStore::save() trusting slots that were lost on the backend (skybleak333)

### Serializer

 * bug #67913 [Serializer] Refuse an object where an array of objects is expected (haraldpdl)
 * bug #67883 [Serializer] Don't read untyped attributes with deep_object_to_populate (nicolas-grekas)
 * bug #67878 [Serializer] Don't call methods named after the attributes being denormalized (nicolas-grekas)
 * bug #67887 [Serializer][Templating] Limit more caches that grow with the input (nicolas-grekas)
 * bug #67888 [Serializer] Limit the caches that grow with unknown keys (nicolas-grekas)
 * bug #67884 [Serializer][Templating] Limit the caches that grow with each input value (nicolas-grekas)
 * bug #67882 [Serializer] Apply ignored attributes to setter and adder aliases (nicolas-grekas)
 * bug #67877 [Serializer] Ignore undeclared property paths with brackets (nicolas-grekas)

### Translation

 * minor #67957 [Translation] Add the license terms of the XML schemas (nicolas-grekas)

### Tui

 * bug #67997 [Tui] Don't leave a style open at the end of a wrapped line (nicolas-grekas)
 * bug #67998 [Tui] Close a hyperlink at the end of a wrapped line and reopen it on the next one (phcorp)
 * bug #67995 [Tui] Don't carry a style that ends where a line wraps over to the next line (phcorp)
 * bug #67893 [Tui] Restore inherited styles after a styled sub-element (nicolas-grekas)
 * bug #67869 [Tui] Keep styled underlines (SGR 4:n) on wrapped lines (phcorp)
 * bug #67868 [Tui] Restore the widget style after a sub-element styled with applyElement() (phcorp)
 * bug #67867 [Tui] Fix the tracked rects of subtrees rendered without position tracking (phcorp)

### TwigBridge

 * bug #68086 [TwigBridge] Remove required class from expanded choice option labels (X-Coder264)
 * bug #67999 [TwigBridge] Check the domain and the position of `trans_default_domain ... for _self` and require Twig 3.29 (nicolas-grekas)

### TypeInfo

 * feature #67856 [TypeInfo][Serializer][JsonStreamer] Resolve templates inherited through ``@extends``, ``@implements`` and ``@use`` tags (nicolas-grekas)
 * bug #68005 [TypeInfo] Fix `Type::fromValue()` for generators that yield boolean keys (nicolas-grekas)
 * bug #67972 [TypeInfo] Fix `Type::fromValue()` for string keys that name a callable (nicolas-grekas)
 * bug #67965 [TypeInfo] Throw `UnsupportedException` on malformed type strings (nicolas-grekas)
 * bug #67964 [TypeInfo] Fix infinite recursion when a type alias import uses another case for the class name (nicolas-grekas)
 * bug #67974 [TypeInfo] Fix infinite recursion in `Type::fromValue()` with cyclic values (lazerg)
 * bug #67955 [TypeInfo] Don't let the templates of a child class shadow the ones of its untagged parent (nicolas-grekas)
 * bug #67951 [TypeInfo] Fix resolving the PHPDoc types of a class with the imports of its traits (nicolas-grekas)
 * bug #67920 [TypeInfo] Keep distinct members when mapping unions and intersections (nicolas-grekas)
 * bug #67918 [TypeInfo] Collect contravariant templates and reduce redundant unions in `Type::map()` (nicolas-grekas)
 * bug #67936 [TypeInfo] Fix resolving the PHPDoc types of trait members (nicolas-grekas)
 * bug #67904 [TypeInfo] Preserve distinct union and intersection members (X-Coder264)
 * minor #67953 [TypeInfo] Cover untagged template inheritance with tests (X-Coder264)

### Validator

 * bug #68097 [Validator] Fix the IBAN formats sync script and synchronize formats (egbavou)
 * bug #67984 [Validator] Fix IBAN format for Côte d'Ivoire (egbavou)
 * bug #67848 [Validator] Sync the non-public subnets of the `Ip` constraint with `IpUtils::PRIVATE_SUBNETS` (nicolas-grekas)

### VarDumper

 * bug #68060 [VarDumper] Don't turn object property names into dump attributes and link only to http(s) URLs (nicolas-grekas)
 * bug #68058 [VarDumper] Escape the content type of images in HtmlDumper (nicolas-grekas)

### WebProfilerBundle

 * bug #68059 [WebProfilerBundle] Ignore debug headers of cross-origin responses and only link to http(s) URLs (nicolas-grekas)

### Workflow

 * bug #68082 [Workflow] Escape backslashes in Graphviz HTML labels (fabpot)

### Yaml

 * bug #68077 [Yaml] Fix anchors, aliases and unsupported entries in flow mapping keys (nicolas-grekas)
 * bug #68069 [Yaml] Keep spaces in plain flow mapping keys (eyupcanakman)
 * bug #67973 [Yaml] Encode schema paths in file URIs (Tong-bit-art)
 * bug #68024 [Yaml] Fix block scalars losing their trailing newline after leading comments (eyupcanakman)
 * bug #67881 [Yaml] Don't copy long nested blocks at each nesting level (nicolas-grekas)
 * bug #67880 [Yaml] Bound the number of nodes that collection aliases expand to (nicolas-grekas)

### Other

 * minor #68085 Missing translations for Albanian (sq) (isihussain)

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
