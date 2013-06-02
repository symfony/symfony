CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

* 2.1.11 (2013-06-02)

 * 2038329: [Form] [Validator] Fixed post_max_size = 0 bug (Issue #8065)
 * 169c0b9: [Finder] Fix iteration fails with non-rewindable streams
 * 45b68e0: [Finder] Fix unexpected duplicate sub path related AppendIterator issue
 * 5321600: Fixed two bugs in HttpCache
 * 5c317b7: [Console] fix and refactor exit code handling
 * 1469953: [CssSelector] Fix :nth-last-child() translation
 * 91b8490: Fix Crawler::children() to not trigger a notice for childless node
 * 0a4837d: Fixed XML syntax.
 * a5441b2: Fixed parsing of leading blank lines in folded scalars. Closes #7989.
 * e8d5d16: Fixed Loader import
 * 37af771: [Console] Added dedicated testcase for HelperSet class

* 2.1.10 (2013-05-06)

 * 5b7e1e6: added a missing check for the provider key
 * bcb5400: [Form] Fixed transform()/reverseTransform() to always throw TransformationFailedExceptions
 * 7b2ebbf: [Form] Fixed: String validation groups are never interpreted as callbacks
 * 0610750: if the repository method returns an array ensure that it's internal poin...
 * 2b554d7: remove validation related headers when needed
 * 2a531d7: Fix getPort() returning 80 instead of 443 when X-FORWARDED-PROTO is set to https
 * 10dea94: [Filesystem] copy() is not working when open_basedir is set
 * 8757ad4: [Process] Fix #5594 : `termsig` must be used instead of `stopsig` in exceptions when a process is signaled
 * 06e21ff: Filesystem::touch() not working with different owners (utime/atime issue)
 * 36d057b: [HttpFoundation][BrowserKit] fixed path when converting a cookie to a string
 * 495d0e3: [HttpFoundation] fixed empty domain= in Cookie::__toString()
 * c2bc707: fixed detection of secure cookies received over https
 * 54bcf5c: [Translator] added additional conversion for encodings other than utf-8
 * 8a434ed: fix a DI circular reference recognition bug
 * 5abf887: Fix default value handling for multi-value options
 * da156d3: fix overwriting of request's locale if attribute _locale is missing
 * d552e4c: [HttpFoundation] do not use server variable PATH_INFO because it is already decoded and thus symfony is fragile to double encoding of the path
 * 047212a: [Yaml] fixed handling an empty value
 * 94a9cdc: [Routing][XML Loader] Add a possibility to set a default value to null
 * 0f0c29c: [HttpFoundation] Fixed bug in key searching for NamespacedAttributeBag
 * 7fc429f: [Form] DateTimeToRfc3339Transformer use proper transformation exteption in reverse transformation
 * 9fcd2f6: [HttpFoundation] fixed the creation of sub-requests under some circumstances for IIS
 * a3826ab: #7531: [HttpKernel][Config] FileLocator adds NULL as global resource path
 * 9d71ebe: Fix autocompletion of command names when namespaces conflict
 * bec8ff1: Fix timeout in Process::stop method
 * bf4a9b0: Round stream_select fifth argument up.
 * 3780fdb: Fix Process timeout
 * 375ded4: [FrameworkBundle] fixed the discovery of the PHPUnit configuration file when using aggregate options like in -vc app/ (closes #7562)
 * 673fd9b: idAsIndex should be true with a smallint or bigint id field.
 * 64a1d39: Fixed long multibyte parameter logging in DbalLogger:startQuery
 * 4cf06c1: Keep the file extension in the temporary copy and test that it exists (closes #7482)
 * c4da2d9: [HttpFoundation] getClientIp is fixed.

* 2.1.9 (2013-03-26)

 * 9875c4b: Added '@@' escaping strategy for YamlFileLoader and YamlDumper
 * bbcdfe2: [Yaml] fixed bugs with folded scalar parsing
 * 5afea04: [Form] made DefaultCsrfProvider using session_status() when available
 * c928ddc: [HttpFoudantion] fixed Request::getPreferredLanguage()
 * e6b7515: [DomCrawler] added support for query string with slash
 * 17dc2ff: [HttpRequest] fixes Request::getLanguages() bug
 * e51432a: sub-requests are now created with the same class as their parent
 * ef53456: [DoctrineBridge] Avoids blob values to be logged by doctrine
 * 6575df6: [Security] use current request attributes to generate redirect url?
 * 7216cb0: [Validator] fix showing wrong max file size for upload errors
 * c423f16: [2.1][TwigBridge] Fixes Issue #7342 in TwigBridge
 * 7d87ecd: [FrameworkBundle] fixed cahe:clear command's warmup
 * fe4cc24: [TwigBridge] fixed fixed scope & trans_default_domain node visitor
 * fc47589: [BrowserKit] added ability to ignored malformed set-cookie header
 * 5bc30bb: [Translation] added xliff loader/dumper with resname support
 * 7241be9: [Finder] fixed a potential issue on Solaris where INF value is wrong (refs #7269)
 * 1d3da29: [FrameworkBundle] avoids cache:clear to break if new/old folders already exist
 * b9cdb9a: [HttpKernel] Fixed possible profiler token collision (closes #7272, closes #7171)
 * d1f5d25: [FrameworkBundle] Fixes invalid serialized objects in cache
 * c82c754: RedisProfilerStorage wrong db-number/index-number selected
 * e86fefa: Unset loading[$id] in ContainerBuilder on exception
 * 73bead7: [ClassLoader] made DebugClassLoader idempotent
 * a4ec677: [DomCrawler] Fix relative path handling in links
 * 6681df0: [Console] fixed StringInput binding
 * 5b19c89: [Console] fixed unparsed StringInput tokens
 * bae83c7: [TwigBridge] fixed trans twig extractor
 * 8f8ba38: [DomCrawler] fix handling of schemes by Link::getUri()
 * 83382bc: [TwigBridge] fixed the translator extractor that were not trimming the text in trans tags (closes #7056)
 * b1ea8e5: Fixed handling absent href attribute in base tag
 * 8d9cd42: Routing issue with installation in a sub-directory ref: https://github.com/symfony/symfony/issues/7129
 * 0690709: added a DebuClassLoader::findFile() method to make the wrapping less invasive
 * 635b1fc: StringInput resets the given options.

* 2.1.8 (2013-02-23)

 * b2080c4: [HttpFoundation] Remove Cache-Control when using https download via IE<9 (fixes #6750)
 * b7bd630: [Form] Fixed TimeType not to render a "size" attribute in select tags
 * 368f62f: Expanded fault-tolerance for unusual cookie dates
 * cb03074: [DomCrawler] lowered parsed protocol string (fixes #6986)
 * 3e40c17: [HttpKernel] fixed locale management when exiting sub-requests
 * 179cd58: [Process] Fix regression introduced in #6620 / 880da01c49a9255f5022ab7e18bca38c18f56370, fixes #7082
 * 18b139d: [FrameworkBundle] tweaked reference dumper command (see #7093)
 * 0eff68f: Fix REMOTE_ADDR for cached subrequests
 * 5e8d844: [Process] Warn user with a useful message when tmpfile() failed
 * 42d3c4c: added support for the X-Forwarded-For header (closes #6982, closes #7000)
 * 6a9c510: fixed the IP address in HttpCache when calling the backend
 * 87f3db7: [EventDispathcer] Fix removeListener
 * e0637fa: [DependencyInjection] Add clone for resources which were introduced in 2.1
 * bd0ad92: [DependencyInjection] Allow frozen containers to be dumped to graphviz
 * 83e9558: Fix 'undefined index' error, when entering scope recursively
 * 3615e19: [Security] fixed session creation on login (closes #7011)
 * a12744e: Add dot character `.` to legal mime subtype regular expression
 * e50d333: [HttpKernel] fixed the creation of the Profiler directory
 * ddf4678: [HttpFoundation] fixed the creation of sub-requests under some circumstancies (closes #6923, closes #6936)
 * 8ca00c5: [Security] fixed session creation when none is needed (closes #6917)
 * 74f2fcf: fixed a circular call (closes #6864)
 * 6f71948: [Yaml] fixed wrong merge (indentation default is 4 as of 2.1)
 * 4119caf: [DependencyInjection] fixed the creation of synthetic services in ContainerBuilder
 * 11aaa2e: Added an error message in the DebugClassLoader when using / instead of \.
 * ce38069: [FrameworkBundle] fixed Client::doRequest that must call its parent method (closes #6737)
 * 53ccc2c: [Yaml] fixed ignored text when parsing an inlined mapping or sequence (closes #6786)
 * ab0385c: [Yaml] fixed #6773
 * fea20b7: [Yaml] fixed #6770

* 2.1.7 (2013-01-17)

 * e17e232: [Yaml] fixed default value
 * 3c87e2e: Added Yaml\Dumper::setIndentation() method to allow a custom indentation level of nested nodes.
 * ba6e315: added a way to enable/disable object support when parsing/dumping
 * ac756bf: added a way to enable/disable PHP support when parsing a YAML input via Yaml::parse()
 * 785d365: fixes a bug when output/error output contains a % character
 * dc2cc6b: [Console] fixed input bug when the value of an option is empty (closes #6649, closes #6689)
 * 9257b03: [Profiler] [Redis] Fix sort of profiler rows.
 * c7bfce9: Fix version_compare() calls for PHP 5.5.
 * 880da01: [Process] In edge cases `getcwd()` can return `false`, then `proc_open()` should get `null` to use default value (the working dir of the current PHP process)
 * 34def9f: Handle the deprecation of IntlDateFormatter::setTimeZoneId() in PHP 5.5.
 * b33d5bc: removed the .gitattributes files (closes #6605, reverts #5674)
 * eb93e66: [Console] Fix style escaping parsing
 * 8ca1b80: [Console] Make style formatter matching less greedy to avoid having to escape when not needed
 * 55aa012: [Form] Fixed EntityChoiceList when loading objects with negative integer IDs
 * 1d362b8: [DependencyInjection] fixed a bug where the strict flag on references were lost (closes #6607)
 * 3195122: [HttpFoundation] Check if required shell functions for `FileBinaryMimeTypeGuesser` are not disabled
 * dbafc2c: [CssSelector] added css selector with empty string
 * 1e2fb64: [DependencyInjection] refactored code to avoid logic duplication
 * d786e09: [Console] made Application::getTerminalDimensions() public
 * 8f21f89: [2.1] [Console] Added getTerminalDimensions() with fix for osx/freebsd
 * 33e9d00: [Form] Deleted references in FormBuilder::getFormConfig() to improve performance
 * ba2d035: Restrict Monolog version to be in version <1.3
 * 4abd5bf: Restrict Monolog version to be in version `<1.3`. Because of conflict between `HttpKernel/Log/LoggerInterface` and `Psr\Log\LoggerInterface` (PSR-3)
 * e0923ae: [DependencyInjection] fixed PhpDumper optimizations when an inlined service depends on the current one indirectly
 * cd15390: [DependencyInjection] fixed PhpDumper when an inlined service definition has some properties
 * fa6fb6f: [Process] Do not reset stdout/stderr pipes on Interrupted system call
 * 73d9cef: [Locale] Adjust `StubIntlDateFormatter` to have new methods added in PHP 5.5
 * d601b13: use the right RequestMatcherInterface
 * 913b564: [Locale] Fix failing `StubIntlDateFormatter` in PHP 5.5
 * 8ae773b: [Form] Fix failing `MonthChoiceList` in PHP 5.5
 * c526ad9: [Form] Fixed inheritance of "error_bubbling" in RepeatedType
 * 6c5e615: [Form] Fixed DateType when used with the intl extension disabled.
 * 10b01c9: [HttpFoundation] fix return types and handling of zero in Response
 * 75952af: [HttpFoundation] better fix for non-parseable Expires header date
 * 87b6cc2: Fix Expires when the header is -1
 * c282a2b: [DoctrineBridge] Allowing memcache port to be 0 to support memcache unix domain sockets.
 * 2fc41a1: [Console] fixed unitialized properties (closes #5935)
 * a5aeb21: [Process] Prevented test from failing when pcntl extension is not enabled.
 * 1d395ad: Revert "[DoctrineBridge] Improved performance of the EntityType when used with the "query_builder" option"
 * ef6f241: [Locale] Fixed the StubLocaleTest for ICU versions lower than 4.8.
 * bfccd28: HttpUtils must handle RequestMatcher too
 * 05fca6d: use preferred_choices in favor of preferred_query
 * 6855cff: add preferred_query option to ModelType
 * 8beee64: [Form] Fix for `DateTimeToStringTransformer`

* 2.1.6 (2012-12-21)

 * b8e5689: [FrameworkBundle] fixed ESI calls
 * ce536cd: [FrameworkBundle] fixed ESI calls

* 2.1.5 (2012-12-20)

 * 532cc9a: [FrameworkBundle] added support for URIs as an argument to HttpKernel::render()
 * 1f8c501: [FrameworkBundle] restricted the type of controllers that can be executed by InternalController
 * 2cd43da: [Process] Allow non-blocking start with PhpProcess
 * 8b2c17f: fix double-decoding in the routing system
 * 098b593: [Session] Added exception to save method
 * ad29df5: [Form] Fixed DateTimeToStringTransformer parsing on PHP < 5.3.8
 * 773d818: [FrameworkBundle] Added a check on file mime type for CodeHelper::fileExcerpt()
 * f24e3d7: [HttpKernel] Revise MongoDbProfilerStorage::write() return value
 * 78c5273: [Session] Document Mongo|MongoClient argument type instead of "object"
 * de19a81: [HttpKernel] Support MongoClient and Mongo connection classes
 * b28af77: [Session] Support MongoClient and Mongo connection classes
 * 20e93bf: [Session] Utilize MongoDB::selectCollection()
 * b20c5ca: [Form] Fixed reverse transformation of values in DateTimeToStringTransformer
 * d2231d8: [Console] Add support for parsing terminal width/height on localized windows, fixes #5742
 * 03b880f: [Form] Fixed treatment of countables and traversables in Form::isEmpty()
 * 21a59ca: [Form] Fixed FileType not to throw an exception when bound empty
 * eac14b5: Check if key # is defined in $value
 * a0e2391: [FrameworkBundle] used the new method for trusted proxies
 * d6a402a: [Security] fixed path info encoding (closes #6040, closes #5695)
 * 47dfb9c: [HttpFoundation] added some tests for the previous merge and removed dead code (closes #6037)
 * 1ab4923: Improved Cache-Control header when no-cache is sent
 * 4e909bd: Fix to allow null values in labels array
 * 9e46819: Fixed: HeaderBag::parseCacheControl() not parsing quoted zero correctly
 * 8bb3208: [Config] Loader::import must return imported data
 * ca5d9ac: [DoctrineBridge] Fixed caching in DoctrineType when "choices" or "preferred_choices" is passed
 * 6e7e08f: [Form] Fixed the default value of "format" in DateType to DateType::DEFAULT_FORMAT if "widget" is not "single_text"
 * 447ff91: [HttpFoundation] changed UploadedFile::move() to use move_uploaded_file() when possible (closes #5878, closes #6185)
 * 0489799: [HttpFoundation] added a check for the host header value
 * b604eb7: [DoctrineBridge] Improved performance of the EntityType when used with the "query_builder" option
 * 99321cb: [DoctrineBridge] Fixed: Exception is thrown if the entity class is not known to Doctrine
 * 2ed30e7: Fixed DefaultValue for session.auto_start in NodeDefinition
 * ae3d531: [TwigBundle] Moved the registration of the app global to the environment

* 2.1.4 (2012-11-29)

 * e5536f0: replaced magic strings by proper constants
 * 6a3ba52: fixed the logic in Request::isSecure() (if the information comes from a source that we trust, don't check other ones)
 * 67e12f3: added a way to configure the X-Forwarded-XXX header names and a way to disable trusting them
 * b45873a: fixed algorithm used to determine the trusted client IP
 * 254b110: removed the non-standard Client-IP HTTP header
 * 06ee53b: [Form] improve error message with a "hasser" hint for PropertyAccessDeniedException
 * ac77c5b: [Form] Updated checks for the ICU version from 4.5+ to 4.7+ due to test failures with ICU 4.6
 * 2fe04e1: Update src/Symfony/Component/Form/Extension/Core/Type/FileType.php
 * bbeff54: Xliff with other node than source or target are ignored
 * 29bfa13: small fix of #5984 when the container param is not set
 * f211b19: Filesystem Component mirror symlinked directory fix
 * 64b54dc: Use better default ports in urlRedirectAction
 * e7401a2: Update src/Symfony/Component/DomCrawler/Tests/FormTest.php
 * b0e468f: Update src/Symfony/Component/DomCrawler/Form.php
 * 1daefa5: [Routing] made it compatible with older PCRE version (pre 8)
 * f2cbea3: [Security] remove escape charters from username provided by Digest DigestAuthenticationListener
 * 82334d2: Force loader to be null or a EntityLoaderInterface
 * 694697d: [Security] Fixed digest authentication
 * c067586: [Security] Fixed digest authentication
 * d2920c9: Added HttpCache\Store::generateContentDigest() + changed visibility
 * e12bd12: [HttpFoundation] Make host & methods really case insensitive in the RequestMacther
 * c659e78: Make YamlFileLoader and XmlFileLoader file loading extensible
 * 0f75586: [Form] Removed an exception that prevented valid formats from being passed, e.g. "h" for the hour, "L" for the month etc.
 * 84b760b: [HttpKernel] fixed Client when using StreamedResponses (closes #5370)
 * 67e697f: fixed PDO session handler for Oracle (closes #5829)
 * c2a8a0b: [HttpFoundation] fixed PDO session handler for Oracle (closes #5829)
 * a30383d: [Locale] removed a check that is done too early (and it is done twice anyways)
 * 84635bd: [Form] allowed no type guesser to be registered
 * 8377146: Adding new localized strings for farsi validation.
 * e34fb41: [HttpFoundation] moved the HTTP protocol check from StreamedResponse to Response (closes #5937)
 * 4909bc3: [Form] Fixed forms not to be marked invalid if their children are already marked invalid
 * dc80385: [Form] Fixed NumberToLocalizedStringTransformer to accept both comma and dot as decimal separator, if possible
 * 208e134: [FrameworkBundle] Router skip defaults resolution for arrays
 * a0af8bf: [Form] Adapted HTML5 format in DateTimeType as response to a closed ICU ticket
 * 6b42c8c: The exception message should say which field is not mapped
 * 9872d26: [HttpFoundation] Fix name sanitization after perfoming move
 * 2d9a6fc: Use Norm Data instead of Data
 * a094f7e: Add check to Store::unlock to ensure file exists

* 2.1.3 (2012-10-30)

 * 6f15c47: [ClassLoader] fixed unbracketed namespaces (closes #5747)
 * 20898e5: Add to DateFormats 'D M d H:i:s Y T' (closes #5830)
 * b844d6b: [Form] Fixed DoctrineOrmTypeGuesser to guess the "required" option for to-one associations
 * 965734e: fixed fallback locale
 * bda29b3: [Form] Fixed error message in PropertyPath to not advice to use a non-existing feature
 * bf3e358: [Form] Fixed creation of multiple money fields with different currencies
 * 8f81f07: [Form] Fixed setting the "data" option to an object in "choice" and "entity" type
 * 53c43bf: Fixed Serbian plural translations.
 * 959c1df: Fixed IPv6 Check in RequestMatcher
 * cf1e02d: [Console] Fix error when mode is not in PATH
 * 6b66bc3: [2.1] Added missing error return codes in commands
 * e0a3fc1: Made the router lazy when setting the context
 * 89f7b5e: [HttpFoundation] fixed empty path when using Request::create() (closes #5729)
 * 8c6b7a4: Fixed the handling of the intl locale when setting the default locale
 * 673f74b: [HttpFoundation] Fixed #5697 - Request::createFromGlobals, Request::getContentType Changed checking CONTENT_TYPE from server to headers variable
 * 1566f9f: [Routing] fix handling of whitespace and synch between collection prefix and route pattern
 * b439d13: fixed DomCrwaler/Form to handle <button> when submitted
 * a4f3ea9: [2.1][DependencyInjection] Incomplete error handling in the container
 * 90145d2: [Routing] fix handling of two starting slashes in the pattern
 * cf422bf: [Validator] Updated swedish translation
 * 132ba25: Update src/Symfony/Component/Validator/Resources/translations/validators.de.xlf
 * 6a6b4ae: Updated lithuanian validation translation
 * 74d10d6: [DomCrawler] Allows using multiselect through Form::setValues().
 * a6ae6f6: [Translation] forced the catalogue to be regenerated when a resource is added (closes symfony/Translation#1)
 * 2568432: [Form] Hardened code of ViolationMapper against errors
 * 6c59fbd: [HttpFoundation] Fixed #5611 - Request::splitHttpAcceptHeader incorrect result order.
 * 2d41229: [Form] Fixed negative index access in PropertyPathBuilder
 * ed1cf54: Update src/Symfony/Component/Validator/Resources/translations/validators.ro.xlf
 * 47d7531: [2.1] Fix SessionHandlerInterface autoloading
 * 1a53b12: [2.0][http-foundation] Fix Response::getDate method
 * 3cc3c67: [DoctrineBridge] Require class option for DoctrineType
 * 4e3ea22: [HttpFoundation] fixed the path to the SensioHandlerInterface class in composer.json
 * 7444cb9: Support the new Microsoft URL Rewrite Module for IIS 7.0
 * c120c4d: Added Base64 encoding, decoding to MongoDBProfilerStorage
 * 335aa86: Update src/Symfony/Component/Validator/Resources/translations/validators.pl.xlf
 * 27b2df9: [Process] Fixed bug introduced by 7bafc69f38a3512eb15aad506959a4e7be162e52.
 * d7623ae: [DomCrawler] Added test for supported encodings by mbstring
 * c812b9d: [Config] Fixed preserving keys in associative arrays
 * c869a65: [Console] Fixed return value for Command::run
 * 2ceebdc: fixed stringification of array objects in RequestDataCollector (closes #5295)
 * b8a2f8c: [HttpFoundation] removed the username and password from generated URL as generated by the Request class (closes #5555)
 * c4429af: [Console] fixed default argument display (closes #5563)

* 2.1.2 (2012-09-20)

 * 7bafc69: Add a Sigchild compatibility mode (set to false by default)
 * 8dd19d8: fix Fatal error: Cannot access private property
 * 3269014: Added Bulgarian translation
 * de6658b: [Profiler]Use the abstract method to get client IP

* 2.1.1 (2012-09-11)

 * fix Composer configuration
