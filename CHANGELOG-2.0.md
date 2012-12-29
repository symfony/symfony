CHANGELOG for 2.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.0 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.0.0...v2.0.1

* 2.0.20 (2012-12-20)

 * 532cc9a: [FrameworkBundle] added support for URIs as an argument to HttpKernel::render()
 * 1f8c501: [FrameworkBundle] restricted the type of controllers that can be executed by InternalController
 * 8b2c17f: fix double-decoding in the routing system
 * 773d818: [FrameworkBundle] Added a check on file mime type for CodeHelper::fileExcerpt()
 * a0e2391: [FrameworkBundle] used the new method for trusted proxies
 * 8bb3208: [Config] Loader::import must return imported data
 * 447ff91: [HttpFoundation] changed UploadedFile::move() to use move_uploaded_file() when possible
 * 0489799: [HttpFoundation] added a check for the host header value
 * ae3d531: [TwigBundle] Moved the registration of the app global to the environment

* 2.0.19 (2012-11-29)

 * e5536f0: replaced magic strings by proper constants
 * 6a3ba52: fixed the logic in Request::isSecure() (if the information comes from a source that we trust, don't check other ones)
 * 67e12f3: added a way to configure the X-Forwarded-XXX header names and a way to disable trusting them
 * b45873a: fixed algorithm used to determine the trusted client IP
 * 254b110: removed the non-standard Client-IP HTTP header
 * fc89d6b: [DependencyInjection] fixed composer.json
 * ac77c5b: [Form] Updated checks for the ICU version from 4.5+ to 4.7+ due to test failures with ICU 4.6
 * 29bfa13: small fix of #5984 when the container param is not set
 * 64b54dc: Use better default ports in urlRedirectAction
 * f2cbea3: [Security] remove escape charters from username provided by Digest DigestAuthenticationListener
 * 694697d: [Security] Fixed digest authentication
 * c067586: [Security] Fixed digest authentication
 * 32dc31e: [SecurityBundle] Convert Http method to uppercase in the config
 * b3a8efd: fixed comment. The parent ACL is not accessed in this method.
 * e12bd12: [HttpFoundation] Make host & methods really case insensitive in the RequestMacther
 * 15a5868: [Validator] fixed Ukrainian language code (closes #5972)
 * dc80385: [Form] Fixed NumberToLocalizedStringTransformer to accept both comma and dot as decimal separator, if possible
 * 9872d26: [HttpFoundation] Fix name sanitization after perfoming move
 * 6f15c47: [ClassLoader] fixed unbracketed namespaces (closes #5747)
 * 2d9a6fc: Use Norm Data instead of Data
 * a094f7e: Add check to Store::unlock to ensure file exists

* 2.0.18 (2012-10-25)

 * 20898e5: Add to DateFormats 'D M d H:i:s Y T' (closes #5830)
 * bf3e358: [Form] Fixed creation of multiple money fields with different currencies
 * 959c1df: Fixed IPv6 Check in RequestMatcher
 * b439d13: fixed DomCrwaler/Form to handle <button> when submitted
 * 22c7a91: [HttpKernel][Translator] Fixed type-hints
 * a6ae6f6: [Translation] forced the catalogue to be regenerated when a resource is added (closes symfony/Translation#1)
 * 6c59fbd: [HttpFoundation] Fixed #5611 - Request::splitHttpAcceptHeader incorrect result order.
 * 1a53b12: [2.0][http-foundation] Fix Response::getDate method
 * 7444cb9: Support the new Microsoft URL Rewrite Module for IIS 7.0. @see http://framework.zend.com/issues/browse/ZF-4491 @see http://framework.zend.com/code/revision.php?repname=Zend+Framework&rev=24842
 * ad95364: hasColorSupport does not take an argument
 * 2ceebdc: fixed stringification of array objects in RequestDataCollector (closes #5295)
 * de6658b: [Profiler]Use the abstract method to get client IP

* 2.0.17 (2012-08-28)

 * 5bf4f92: fixed XML decoding attack vector through external entities
 * 4e0c992: prevents injection of malicious doc types
 * 47fe725: disabled network access when loading XML documents
 * c896d71: refined previous commit
 * a2a6cdc: prevents injection of malicious doc types
 * 865461d: standardized the way we handle XML errors
 * 352e8f5: Redirects are now absolute
 * 9a355e9: [HttpKernel] excluded a test on PHP 5.3.16, which is buggy (PHP, not Symfony ;))
 * f694615: [Process] fix ProcessTest::testProcessPipes hangs on Windows on branch 2.0
 * 9beffff: [HttpKernel] KernelTest::testGetRootDir fails on Windows for branch 2.0
 * e49afde: Update monolog compatibility
 * 832f8dd: Add support for Monolog 1.2.0
 * c51fc10: avoid fatal error on invalid session
 * 1a4a4ee: [DependencyInjection] Fixed a frozen constructor of a container with no parameters
 * b3cf36a: [Config] Missing type argument passed to loader.
 * 55a0b34: Fixes incorrect class used in src/Symfony/Bundle/FrameworkBundle/Console/Application.php
 * a0709fc: [DoctrineBridge] Fix log of non utf8 data
 * 0b78fdf: Only call registerCommand on bundles that is an instance of Bundle
 * 9e28593: fixed error on oracle db related to clob data.
 * 9f4178b: [Validator] Fixed: StaticMethodLoader does not try to invoke methods of interfaces anymore
 * 2a3235a: [Validator] Fixed group sequence support in the XML and YAML drivers
 * 4f93d1a: [Console] Use proc_open instead of exec to suppress errors when run on windows and stty is not present
 * 16a980b: [Validator] Fix bug order for constraint, property, getter and group-sequence-provider in validation.xml
 * ed8823c: [HttpFoundation] Allow setting an unknown status code without specifying a text
 * e9d799c: [Routing] fixed ApacheUrlMatcher and ApachMatcherDumper classes that did not take care of default parameters in urls.

* 2.0.16 (2012-07-11)

 * 854daa8: [Form] Fixed errors not to be added onto non-synchronized forms
 * facbcdc: [Validator] fixed error message for dates like 2012-02-31 (closes #4223)
 * 28f002d: [Locale] fixed bug on the parsing of TYPE_INT64 integers in 32 bit and 64 bit environments, caused by PHP bug fix :) (closes #4718)
 * c1fea1d: fixed incorrect reference to set*Service() method
 * b89b00f: bumped minimal version of Swiftmailer to 4.2.0
 * 997bcfc: [SwiftmailerBridge] allowed versions 4.2.*
 * 680b83c: [Security] Allow "0" as a password
 * a609d55: [Locale] fixed StubIntlDateFormatter to behave like the ext/intl implementation
 * 3ce8227: [Security] Only redirect to urls called with http method GET
 * ba16a51: changed getName() to name on all Reflection* object calls (fixes #4555, refs https://bugs.php.net/bug.php?id=61384)
 * 5d88255: Authorization header should only be rebuild when Basic Auth scheme is used
 * 789fc14: Accept calling setLenient(false)
 * b631073: [Yaml] Fixed double quotes escaping in Dumper.

* 2.0.15 (2012-05-30)

 * 20b556d: [Form] fixed a bug that caused input date validation not to be strict when using the single_text widget with a datetime field
 * 7e3213c: [Form] fixed a bug that caused input date validation not to be strict when using the single_text widget with a date field
 * 35b458f: fix kernel root, linux dir separator on windows, to fix cache:clear issue
 * 8da880c: Fixed notice in AddCacheWarmerPass if there is no cache warmer defined.
 * 7a85b43: [TwigBundle] Fixed the path to templates when using composer
 * 8223632: [HttpFoundation] Fix the UploadedFilename name sanitization (fix #2577)
 * f883953: TypeGuess fixed for Date/Time constraints
 * 41bed29: [Form] fixed invalid 'type' option in ValidatorTypeGuesser for Date/TimeFields
 * fff7221: Fixed the proxy autoloading for Doctrine 2.2
 * a450d00: [HttpFoundation] HTTP Basic authentication is broken with PHP as cgi/fastCGI under Apache

* 2.0.14 (2012-05-17)

 * d1c831d: Change must-proxy-revalidate by proxy-revalidate
 * 445fd2f: In console terms columns are width and rows are height
 * 926ac98: [Finder] replaced static by self on a private variable
 * 47605f6: [Form][DataMapper] Do not update form to data when form is read only
 * c642a5e: [CssSelector] ignored an optional whitespace after a combinator
 * cbc3ed3: [HttpKernel] added some constant for better forward compatibility
 * 906f6f6: [DependencyInjection] fixed private services removal when used as configurators (closes #3758)
 * 970d0b4: [BrowserKit] Check class existence only when required.
 * 1ed8b72: Autoloader should not throw exception because PHP will continue to call other registered autoloaders.
 * 7fe236a: [Security] Configure ports in RetryAuthenticationEntryPoint according to router settings

* 2.0.13 (2012-04-30)

 * 5b92b9e: [Console] Selectively output to STDOUT or OUTPUT stream
 * c89f3d3: [HttpKernel] Added DEPRECATED errors
 * 689a40d: [MonologBridge] Fixed the WebProcessor
 * 2e7d3b1: http_build_query fix
 * de73de0: http_build_query fix
 * 3b7ee9a: http_build_query fix
 * 14b3b05: [TwigBundle] added missing entry in the XSD schema
 * 7ddc8cb: [FrameworkBundle] Monitor added/removed translations files in dev (fix #3653)
 * 686653a: [HttpKernel] Fixed wache vary write (fixes #3896).
 * 45ada32: Add Support for boolean as to string into yaml extension
 * cd783fb: [HttpKernel] Fixed cache vary lookup (fixes #3896).
 * 3939c90: [FrameworkBundle] Fix TraceableEventDispatcher unable to trace static class callables
 * e4cbbf3: [Locale] fixed StubNumberFormatter::format() to behave like the NumberFormatter::parse() regarding to error flagging
 * f16ff89: [Locale] fixed StubNumberFormatter::parse() to behave like the NumberFormatter::parse() regarding to error flagging
 * 0a60664: [Locale] updated StubIntlDateFormatter::format() exception message when timestamp argument is an array for PHP >= 5.3.4
 * 6f9c05d: [Locale] Complete Stub with intl_error_name
 * 312a5a4: [Locale] fixed StubIntlDateFormatter::format() to set the right error for PHP >= 5.3.4 and to behave like the intl when formatting successfully
 * bb61e09: [Locale] use the correct way for Intl error
 * 01fcb08: [HttpKernel] Fix the ProfilerListener (fix #3620)
 * 3ae826a: Fix issue #3251: Check attribute type of service tags
 * 57dd914: [EventDispatcher] Fixed E_NOTICES with multiple eventnames per subscriber with mixed priorities
 * 77185e0: [Routing] Allow spaces in the script name for the apache dumper
 * 6465a69: [Routing] Fixes to handle spaces in route pattern
 * 31dde14: [Locale] updated StubIntlDateFormatter::format() behavior for PHP >= 5.3.4
 * 8a2b115: [Console] Mock terminal size to prevent formatting errors on small terminals
 * 595cc11: [Console] Wrap exception messages to the terminal width to avoid ugly output
 * 97f7b29: [Console] Avoid outputing \r's in exception messages
 * 04ae7cc: [Routing] fixed exception message.
 * f7647f9: [Routing] improved exception message when giving an invalid route name.
 * 0024ddc: Fix for using route name as check_path.
 * fc41d4f: [Security] [HttpDigest] Fixes a configuration error caused by an invalid 'key' child node configuration
 * 24a0d0a: [DependencyInjection] Support Yaml calls without arguments
 * 15dd17e: Simplified CONTENT_ headers retrieval
 * 86a3512: [FrameworkBundle] Add support for full URLs to redirect controller
 * 068e859: [TwigBundle] Changed getAndCleanOutputBuffering() handling of systems where ob_get_level() never returns 0
 * efa807a: [HttpKernel] fixed sub-request which should be always a GET (refs #3657)
 * c1206c3: [FrameworkBundle] Subrequests should always use GET method
 * 0c9b2d4: use SecurityContextInterface instead of SecurityContext

* 2.0.12 (2012-03-19)

 * 54b2413: Webprofiler ipv6 search fix
 * 8642473: Changed instances of \DateTimeZone::UTC to 'UTC' as the constant is not valid a produces this error when DateTimeZone is instantiated: DateTimeZone::__construct() [<a href='datetimezone.--construct'>datetimezone.--construct</a>]: Unknown or bad timezone (1024)
 * fbed9ff: Update src/Symfony/Component/HttpKernel/HttpCache/HttpCache.php
 * 1b395f5: Revert "Throw exception when "date_widget" option is not equal to "time_widget""
 * ed218bb: Fixed an "Array to string conversion" warning when using PHP 5.4. Also affects Symfony2 master.
 * 50cb486: Fixed proxy generation in the DoctrineBundle when using Doctrine >= 2.2.0
 * 93cc9ef: [Validator] Remove a race condition in the ClassMetaDataFactory (fix #3217)
 * 878c239: Fixed autoloader leakage in tests
 * 17c3482: fixed timezone bug in DateTimeToTimestampTransformer
 * 705e460: provided unmerged definition for correct help generation
 * 45bbb5b: added getNativeDefinition() to allow specifying an alternate InputDefinition for help generation
 * aa53b88: Sets _format attribute only if it wasn't set previously by the user
 * a827375: [CssSelector] fixed CssSelector::toXPath() when the CSS selector is an empty string
 * ad07a95: [BrowserKit] Fixed Client->back/forward/reload() not keeping all request attributes
 * eee5065: [TwigBundle] Workaround a flaw in the design of the configuration (normalization)
 * 7aad478: [Locale] Prevent empty bundle
 * a894431: [DependencyInjection] Allow parsing of parameters near escaped percent signs
 * f758884: [FrameworkBundle] ContainerAwareEventDispatcher::removeListener() (closes #3115)
 * 8fe6ee3: [Console] fixed help command when used from the shell (closes #3480)
 * caa44ae: Only work with the cli sapi
 * e2fc3cd: [Process] PHP_BINARY return the current process
 * dc2d5a0: [HttpFoundation][Session] Fix bug in PDO Session Storage with SQLSRV making assumptions about parameters with length being OUTPUT not INPUT parameters.
 * e8281cf: SqliteProfilerStorage fix

* 2.0.11 (2012-02-24)

 * 3e64d36: [Serializer] Fix XML decoding attack vector through external entities
 * 66d0d3d: [FrameworkBundle] Fix a bug in the RedirectableUrlMatcher
 * 24a3cd3: Finder - allow sorting when searching in multiple directories
 * 6e75fd1: Resolves issue with spl_autoload_register creating new copies of the container and passing that into the closure.
 * d02ca25: [MonologBundle] Fixed a bug when adding a processor on a service handler
 * 2434552: [Translation] Fixed fallback location if location is longer than three characters (possibly by mistake).
 * ec7fb0b: [Routing] added a proper exception when a route pattern references the same variable more than once (closes #3344)
 * beb4fc0: [WIP][Locale] StubIntlDateFormatter::parse was throwing exception instead of returning Boolean false like intl implementation

* 2.0.10 (2012-02-06)

 * 8e13095: Fixed the unescaping of parameters to handle arrays
 * c3f0ec7: Make DoctrineBundle fowards compatible with Doctrine 2.2
 * e814d27: [FormType] Fixed broken MoneyType regexp for JPY
 * 7f96c8a: [HttpKernel] Prevent php script execution in cached ESI pages using HttpCache
 * 959614b: Use reflection to determaine the correct path for component validation.xml file
 * cacc880: [Bugfix][Locale] Fixed incomplete Locale data loading
 * d67d419: [HttpFoundation] added missing trustProxy condition
 * efce640: [Yaml][Parser] throw an exception if not readable
 * aa58330: [Form] fixed flawed condition
 * 253eeba: [BugFix][Validator] Fix for PHP incosistent behaviour of ArrayAccess
 * 0507840: Prevent parameters from overwriting the template filename.
 * 9bc41d0: [HttpFoundation] Fixed #3053
 * 9441c46: [DependencyInjection] PhpDumper, fixes #2730

* 2.0.9 (2012-01-06)

 * 0492290: [Console] added a missing method (closes #3043)
 * e09b523: updated Twig to 1.5.1 to fix a regression
 * 261325d: Cast $query['params'] to array to ensure it is a valid argument for the foreach.
 * 85ca8e3: ParameterBag no longer resolves parameters that have spaces.
 * aacb2de: use the forward compat version in the Filesystem service
 * 41950a6: [WebProfilerBundle] add margin-bottom to caption

* 2.0.8 (2011-12-26)

 * adea589: [Twig] made code compatible with Twig 1.5
 * 6e98730: added forwards compatibility for the Filesystem component
 * 1b4aaa2: [HttpFoundation] fixed ApacheRequest
 * 8235848: [HttpFoundation][File] Add flv file default extension
 * 5a6c989: FrameworkBundle: Adding test-attribute in xsd-schema to write functional-tests if using xml-configurations
 * 649fa52: [DoctrineBridge] Fixed the entity provider to support proxies
 * e417153: [BugFix][Console] Fix type hint for output formatter
 * d1fa8cc: [WebProfiler] Fix some design glitches (closes #2867)
 * 662fdc3: [DoctrineBundle] Fixed incorrectly shown params
 * 9e38d6a: [SwiftmailerBundle] fixed the send email command when the queue does not extends Swift_ConfigurableSpool
 * 5c41ec9: [HttpKernel][Client] Only simple (name=value without any other params) cookies can be stored in same line, so lets add every as standalone to be compliant with rfc6265
 * 8069ea6: [Form] Added missing use statements (closes #2880)
 * d5a1343: [Console] Improve input definition output for Boolean defaults
 * 62f3dc4: [SecurityBundle] Changed environment to something unique.
 * 0900ecc: #2688: Entities are generated in wrong folder (doctrine:generate:entities Namespace)
 * f3e92c4: [TwigBundle] Fix the exception message escaping
 * 4d64d90: Allow empty result; change default *choices* value to **null** instead of **array()**. - added *testEmptyChoicesAreManaged* test - `null` as default value for choices. - is_array() used to test if choices are user-defined. - `null` as default value in __construct too. - `null` as default value for choices in EntityType.
 * ec7eec5: [DependencyInjection] fixed espacing issue (close #2819)
 * 6548354: fixed data-url
 * d97d7e9: Added a check to see if the type is a string if it's not a FormTypeInterface
 * 7827f72: Fixes #2817: ensure that the base loader is correctly initialised
 * 9c1fbb8: [DoctrineBridge] fixed the refreshing of the user for invalid users
 * 45bba7b: Added a hint about a possible cause for why no mime type guesser is be available
 * 3759ff0: [Locale] StubNumberFormatter allow to parse 64bit number in 64bit mode
 * db2d773: [FrameworkBundle] Improve the TemplateLocator exception message
 * 2c3e9ad: [DependencyInjection] Made the reference case insensitive
 * 4535abe: [DoctrineBridge] Fixed attempt to serialize non-serializable values

* 2.0.7 (2011-12-08)

 * b7fd519: [Security] fixed cast
 * acbbe8a: [Process] introduced usage of PHP_BINARY (available as of PHP 5.4)
 * 03ed770: [Validator] The current class isn't set in execution context when doing validateProperty()
 * 7cfc392: check for session before trying to authentication details
 * 3c83b89: [DoctrineBridge] Catch user-error when the identifier is not serialized with the User entity.
 * 769c17b: Throw exceptions in case someone forgot to set method name in call.
 * 4a8f101b: Fixed problem with multiple occurences of a given namespace. fix #2688
 * 63e2a99: [CssSelector] Fixed Issue for XPathExprOr: missing prefix in string conversion
 * 36c7d03: Fixed GH-2720 - Fix disabled atrribute handling for radio form elements
 * 17dc605: [FrameworkBundle] Checks that the template is readable before checking its modification time
 * 61e0bde: [HttpKernel] ControllerResolver arguments reflection for Closure object.
 * e06cea9: [HttpFoundation] Cookie values should not be restricted
 * a931e21: get correct client IP from X-forwarded-for header
 * 78e9b2f: [Form] Fixed textarea_widget (W3C standards)
 * 36cebf0: Fix infinite loop on circular reference in form factory
 * 79ae3fc: [Form] fixed radio and checkbox when data is not bool
 * c1426ba: added locale handling forward compatibility
 * 10eed30: added MessageDataCollector forward compatibility
 * 57e1aeb: Fixed undefined index notice in readProperty() method (PropertyPath)

* 2.0.6 (2011-11-16)

 * f7c5bf1: [HttpKernel] fixed Content-Length header when using ESI tags (closes #2623)
 * d67fbe9: [HttpFoundation] added an exception to MimeTypeGuesser::guess() when no guesser are available (closes #2636)
 * 0462a89: [Security] fixed HttpUtils::checkRequestPath() to not catch all exceptions (closes #2637)
 * 24dcd0f: [DoctrineBundle] added missing default parameters, needed to setup and use DBAL without ORM
 * 462580c: [Form] Check for normal integers. refs 0427b126c15a0a27cd7033375e30371ae6a4e516
 * bb5fb79: changed the way we store the current ob level (refs #2617)
 * fb0fffe: [Validator] fixed a unit test for PHP 5.4 (closes #2585)
 * 7cba0a0: Also identify FirePHP by the X-FirePHP-Version header
 * ed1a6c2: [TwigBundle] Do not clean output buffering below initial level
 * e83e00a: Fixed rendering of FileType (value is not a valid attribute for input[type=file])
 * 8351a11: Added check for array fields to be integers in reverseTransform method. This prevents checkdate from getting strings as arguments and throwing incorrect ErrorException when submitting form with malformed (string) data in, for example, Date field. #2609
 * 45b218e: [Translation] added detection for circular references when adding a fallback catalogue
 * a245e15: [DomCrawler] trim URI in getURI
 * 9d2ab9c: [Doctrine] fixed security user reloading when the user has been changed via a form with validation errors (closes #2033)
 * d789f94: Serializer#normalize gives precedence to objects that support normalization
 * 57b7daf: [Security] Fix checkRequestPath doc; closes #2323
 * b33198f: fixed CodeHelper::formatFileFromText() method to allow &quot; as a file wrapper (it occurs for the main exception message)
 * c31c512: [FrameworkBundle] fixed output buffering when an error occurs in a sub-request
 * 380c67e: [FrameworkBundle] fixed HttpKernel when the app is stateless
 * 95a1902: [Finder] bypassed some code when possible
 * 957690c: fixing WebTastCase when kernel is not found and improving exception message
 * dbba796: [Yaml] fixed dumper for floats when the locale separator is not a dot
 * f9befb6: Remove only the security token instead of the session cookie.
 * 348bccb: Clear session cookie if user was deleted, is disabled or locked to prevent infinite redirect loops to the login path (fixes #1798).
 * 89cd64a: Set error code when number cannot be parsed. Fixes #2389
 * c9d05d7: Let NumberFormatter handle integer type casting

* 2.0.5 (2011-11-02)

 * c5e2def: Fix ternary operator usage in RequestMatcher::checkIpv6()
 * 43ce425: [HttpKernel] added missing accessor
 * 80f0b98: [DependencyInjection] Fix DefinitionDecorator::getArgument() for replacements
 * 4bd340d: [Security] Fix typo in init:acl command name
 * 3043fa0: [HttpFoundation] fixed PHP 5.4 regression
 * 8dcde3c: [DependencyInjection] fixed int casting for XML files (based on what is done in the YAML component)
 * 6c2f093: [HttpFoundation] removed superfluous query call (closes #2469)
 * 6343bef: [HttpKernel] Updated mirror method to check for symlinks before dirs and files
 * 27d0809: [MonologBridge] Adjust for Monolog 1.0.2
 * 808088a: added the ability to use dot and single quotes in the keys and values
 * cbb4bba: [Routing] fixed side-effect in the PHP matcher dumper
 * 1a43505: [FrameworkBundle] fixed priority to be consistent with 2.1
 * 6b872cf: Check if cache_warmer service is available before doing the actual cache warmup
 * e81c710: Increased the priority of the profiler request listener
 * 2b0af5e: [HttpKernel] fixed profile parent/children for deep-nested requests
 * 9d8046e: [Doctrine] GH-1635 - UniqueValidator now works with associations
 * 3426c83: [BrowserKit] fixed cookie updates from Response (the URI here is not the base URI, so it should not be used to determine the default values missing in the cookie, closes #2309)
 * c0f5b8a: [HttpKernel] fixed profile saving when it has children
 * 3d7510e: [HttpKernel] fixed missing init for Profile children property
 * 00cbd39: [BrowserKit] Fixed cookie expiry discard when attribute contains capitals
 * edfa29b: session data needs to be encoded because it can contain non binary safe characters e.g null. Fixes #2067
 * c00ba4d: [Console] fixed typo (closes #2358)
 * 2270a4d: [Bridge][Doctrine] Adding a catch for when a developer uses the EntityType with multiple=false but on a "hasMany" relationship
 * 2877883: anything in front of ;q= is part of the mime type, anything after may be ignored
 * d2d849c: Added translations for "hy"
 * ae0685a: [Translation] Loader should only load local files
 * 8bd0e42: [Form] Use proper parent (text) for EmailType and TextareaType
 * 95049ef: [Form] Added type check to `ScalarToChoiceTransformer`
 * a74ae9d: [HttpFoundation] made X_REWRITE_URL only available on Windows platforms
 * 828b18f: [Form] Fixed lacking attributes in DateTimeType

* 2.0.4 (2011-10-04)

 * cf4a91e: [ClassLoader] fixed usage of trait_exists()
 * 8d6add6: [DoctrineBridge] fixed directory reference when the directory cannot be created
 * 5419638: [HttpKernel] Show the actual directory needing to be created.
 * 5c8a2fb: [Routing] fixed route overriden mechanism when using embedded collections (closes #2139)
 * e70c884: [Bridge/Monolog] Fix WebProcessor to accept a Request object.
 * 600b8ef: [Validator] added support for grapheme_strlen when mbstring is not installed but intl is installed
 * d429594: removed separator of choice widget when the separator is null
 * 17af138: fixed usage of LIBXML_COMPACT as it is not always available
 * b12ce94: [HttpFoundation] fix #2142 PathInfo parsing/checking
 * b402835: [HttpFoundation] standardized cookie paths (an empty path is equivalent to /)
 * 1284681: [BrowserKit] standardized cookie paths (an empty path is equivalent to /)
 * 1e7e6ba: [HttpFoundation] removed the possibility for a cookie path to set it to null (as this is equivalent to /)
 * 2db24c2: removed time limit for the vendors script (closes #2282)
 * c13b4e2: fixed fallback catalogue mechanism in Framework bundle
 * 369f181: [FrameworkBundle] Add request scope to assets helper only if needed
 * d6b915a: [FrameworkBundle] Assets templating helper does not need request scope
 * ed02aa9: Fix console: list 'namespace' command display all available commands
 * 85ed5c6: [ClassLoader] Fixed state when trait_exists doesn't exists
 * e866a67: [DoctrineBundle] Tries to auto-generate the missing proxy files on the autoloader
 * 908a7a3: [HttpFoundation] Fix bug in clearCookie/removeCookie not clearing cookies set with a default '/' path, unless it was explicitly specified

* 2.0.3 (2011-09-25)

 * 49c585e: Revert "merged branch stealth35/ini_bool (PR #2235)"

* 2.0.2 (2011-09-25)

 * ae3aded: Added PCRE_DOTALL modifier to RouteCompiler to allow urlencoded linefeed in route parameters.
 * e5a23db: [ClassLoader] added support for PHP 5.4 traits
 * 11c4412: [DependencyInjection] fix 2219 IniFileLoader accept Boolean
 * 64d44fb: [Translator] fixed recursion when using a fallback that is the same as the locale
 * bca551e: [DomCrawler] ChoiceFormField should take the content when value is unavailable
 * b635dca: [Translator] fixed non-loaded locale
 * ab8e760: Fixed the creation of the subrequests
 * 8e2cbe6: fixes usage of mb_*
 * fd4d241: Profiler session import fixed.
 * 9fb15c7: [Process] workaround a faulty implementation of is_executable on Windows
 * 43b55ef: [Locale] Fix #2179 StubIntlDateFormatter support yy format
 * 9ffd8ca: [Translation] renamed hasStrict() to defines()
 * 79710ed: [Translation] added a MessageCatalogue::hasStrict() method to check if a string has a translation (but without taking into account the fallback mechanism)
 * c50a3a1: [Translation] fixed transchoice when used with a fallback
 * c985ffa: [Translation] fixed message selector when the message is empty (closes #2144)
 * 27ba003: [HttpFoundation] changed the strategy introduced in a5ccda47b4406518ee75929ce2e690b6998c021b to fix functional tests and still allow to call save more than once for a Session
 * ff99d80: Changed the behavior of Session::regenerate to destroy the session when it invalidates it.
 * 73c8d2b: [Form] fixed error bubbling for Date and Time types when rendering as multiple choices (closes #2062)
 * 95dc7e1: Fixed fourth argument of Filesystem->mirror()
 * ae52303: [HttpFoundation] Fixed duplicate of request
 * cd40ed4: Added missing method to HTTP Digest entry point
 * 3a7e038: [FrameworkBundle] sanitize target arg in asset:install command
 * 8d50c16: few optimisations for XliffFileLoader and XmlFileLoader
 * 639513a: Per the documentation, the `NotBlank` constraint should be using the `empty` language construct, otherwise it will not trigger on, for example, a boolean false from an unchecked checkbox field.
 * d19f1d7: [Doctrine] Fix UniqueEntityValidator reporting a false positive by ignoring multiple query results
 * 0224a34: Fixes typo on ACL Doctrine cache.
 * 6bd1749: Fixed a bug when multiple expanded choices would render unchecked because of the Form Framework's strict type checking.
 * f448029: [HttpKernel] Tweaked SQLite to speed up SqliteProfilerStorage
 * 2cfa22c: Fix Method ContainerAwareEventDispatcher::hasListeners
 * f4c133e: removed trailing dot to make it consistent with other validator messages
 * a6670c2: [Routing] fixed a caching issue when annotations are used on a base class used by more than one concrete class
 * 946ccb6: [Routing] fixed annotation loaders for abstract classes, added more unit tests
 * 723cb71: [Translation] Add compatibility to PCRE 6.6.0 for explicit interval pluralization
 * 24bacdc: Ignore VCS files in assets:install command (closes #2025)
 * 020fa51: [RedirectResponse] Added missing `doctype` and `title` tag

* 2.0.1 (2011-08-26)

 * 1c7694f: [HttpFoundation] added a missing exception
 * 84c1719: [FrameworkBundle] Avoid listener key conflicts in ContainerAwareEventDispatcher
 * 536538f: [DoctrineBundle] removed an unused and confusing parameter (the connection class can be changed via the wrapper_class setting of a connection)
 * d7f0789: [FrameworkBundle] fixed duplicated RequestContext instances
 * 89f477e: [WebProfilerBundle] Throw exception if a collector template isn't found
 * 6ca72cf: [WebProfilerBundle] Allow .html.twig in collector template names
 * 39fabab: [EventDispatcher] Fix removeSubscriber() to work with priority syntax
 * 3380f2a: [DomCrawler] fixed disabled fields in forms (they are available in the DOM, but their values are not submitted -- whereas before, they were simply removed from the DOM)
 * 2b1bb2c: [Form] added missing DelegatingValidator registration in the Form Extension class (used when using the Form component outside a Symfony2 project where the validation.xml is used instead)
 * fdd2e7a: [Form] Fixing a bug where setting empty_value to false caused a variable to not be found
 * bc7edfe: [FrameworkBundle] changed resource filename of Japanese validator translation
 * c29fa9d: [Form] Fix for treatment zero as empty data. Closes #1986
 * 6e7c375: [FrameworkBundle] Cleanup schema file
 * b6ee1a6: fixes a bug when overriding method via the X-HTTP-METHOD-OVERRIDE header
 * 80d1718: [Fix] Email() constraints now guess as 'email' field type
 * 3a64b08: Search in others user providers when a user is not found in the first user provider and throws the right exception.
 * 805a267: Remove Content-Length header adding for now. Fixes #1846.
 * ae55a98: Added $format in serialize() method, to keep consistence and give a hint to the normalizer.
 * 7ec533e: got an if-condition out of unnecessary loops in Symfony\Component\ClassLoader\UniversalClassLoader
 * 34a1b53: [HttpFoundation] Do not save session in Session::__destroy() when saved already
 * 81fb8e1: [DomCrawler] fix finding charset in addContent
 * 4f9d229: The trace argument value could be string ("*DEEP NESTED ARRAY*")
 * be031f5: [HttpKernel] fixed ControllerResolver when the controller is a class name with an __invoke() method
 * 275da0d: [Validator] changed 'self' to 'static' for child class to override pattern constant
 * e78bc32: Fixed: Notice: Undefined index: enable_annotations in ...
 * 86f888f: fix https default port check
 * 8a980bd: $node->hasAttribute('disabled') sf2 should not create disagreement between implementation and practice for a crawler. If sahi real browser can find an element that is disabled, then sf2 should too. https://github.com/Behat/Mink/pull/58#issuecomment-1712459
 * 1087792: -- fix use of STDIN
 * ee5b9ce: [SwiftmailerBundle] Allow non-file spools
 * d880db2: [Form] Test covered fix for invalid date (13 month/31.02.2011 etc.) send to transformer. Closes #1755
 * df74f49: Patched src/Symfony/Component/Form/Extension/Core/DataTransformer/DateTimeToArrayTransformer.php to throw an exception when an invalid date is passed for transformation (e.g. 31st February)
 * 8519967: Calling supportsClass from vote to find out if we can vote

* 2.0.0 (2011-07-28)
