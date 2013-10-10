CHANGELOG for 2.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.2.0...v2.2.1

* 2.2.9 (2013-10-10)

 * [Security] limited the password length passed to encoders
 * bug #9237 [FrameworkBundle] assets:install command should mirror .dotfiles (.htaccess) (FineWolf)
 * bug #9223 [Translator] PoFileDumper - PO headers (Padam87)
 * bug #9257 [Process] Fix 9182 : random failure on pipes tests (romainneutron)
 * bug #9222 [Bridge] [Propel1] Fixed guessed relations (ClementGautier)
 * bug #9214 [FramworkBundle] Check event listener services are not abstract (lyrixx)
 * bug #9207 [HttpKernel] Check for lock existence before unlinking (ollietb)
 * bug #9184 Fixed cache warmup of paths which contain back-slashes (fabpot)
 * bug #9192 [Form] remove MinCount and MaxCount constraints in ValidatorTypeGuesser (franek)
 * bug #9190 Fix: duplicate usage of Symfony\Component\HttpFoundation\Response (realsim)
 * bug #9188 [Form] add support for Length and Range constraint in ValidatorTypeGuesser (franek)
 * bug #8809 [Form] enforce correct timezone (Burgov)
 * bug #9169 Fixed client insulation when using the terminable event (fabpot)
 * bug #9154 Fix problem with Windows file links (backslash in JavaScript string) (fabpot)
 * bug #9103 [HttpFoundation] Header `HTTP_X_FORWARDED_PROTO` can contain various values (stloyd)

* 2.2.8 (2013-09-25)

 * same as 2.2.7

* 2.2.7 (2013-09-25)

 * 8980954: bugix: CookieJar returns cookies with domain "domain.com" for domain "foodomain.com"
 * 3108c71: [Locale] added support for the position argument to NumberFormatter::parse()
 * 0774c79: [Locale] added some more stubs for the number formatter
 * e5282e8: [DomCrawler]Crawler guess charset from html
 * 0e80d88: fixes RequestDataCollector bug, visible when used on Drupal8
 * c8d0342: [Console] fixed exception rendering when nested styles
 * a47d663: [Console] fixed the formatter for single-char tags
 * c6c35b3: [Console] Escape exception message during the rendering of an exception
 * 0e437c5: [BrowserKit] Fixed the handling of parameters when redirecting
 * 958ec09: NativeSessionStorage regenerate
 * 0d6af5c: Use setTimeZone if this method exists.
 * 773e716: [HttpFoundation] Fixed the way path to directory is trimmed.
 * 42019f6: [Console] Fixed argument parsing when a single dash is passed.
 * b591419: [HttpFoundation] removed double-slashes (closes #8388)
 * 4f5b8f0: [HttpFoundation] tried to keep the original Request URI as much as possible to avoid different behavior between ::createFromGlobals() and ::create()
 * 4c1dbc7: [TwigBridge] fixed form rendering when used in a template with dynamic inheritance
 * 8444339: [HttpKernel] added a check for private event listeners/subscribers
 * ce7de37: [DependencyInjection] fixed a non-detected circular reference in PhpDumper (closes #8425)
 * 37102dc: [Process] Close unix pipes before calling `proc_close` to avoid a deadlock
 * 8c2a733: [HttpFoundation] fixed format duplication in Request
 * 1e75cf9: [Process] Fix #8970 : read output once the process is finished, enable pipe tests on Windows
 * ed83752: [Form] Fixed expanded choice field to be marked invalid when unknown choices are submitted
 * 30aa1de: [Form] Fixed ChoiceList::get*By*() methods to preserve order and array keys
 * 49f5027: [HttpKernel] fixer HInclude src (closes #8951)
 * c567262: Fixed escaping of service identifiers in configuration
 * 4a76c76: [Process][2.2] Fix Process component on windows
 * 65814ba: Request->getPort() should prefer HTTP_HOST over SERVER_PORT
 * e75d284: Fixing broken http auth digest in some circumstances (php-fpm + apache).
 * 899f176: [Security] fixed a leak in ExceptionListener
 * 2fd8a7a: [Security] fixed a leak in the ContextListener
 * 4e9d990: Ignore posix_istatty warnings
 * 2d34e78: [BrowserKit] fixed method/files/content when redirecting a request
 * 64e1655: [BrowserKit] removed some headers when redirecting a request
 * 96a4b00: [BrowserKit] fixed headers when redirecting if history is set to false (refs #8697)
 * c931eb7: [HttpKernel] fixed route parameters storage in the Request data collector (closes #8867)
 * 96bb731: optimized circular reference checker
 * 91234cd: [HttpKernel] changed fragment URLs to be relative by default (closes #8458)
 * 4922a80: [FrameworkBundle] added support for double-quoted strings in the extractor (closes #8797)
 * 0d07af8: [BrowserKit] Pass headers when `followRedirect()` is called
 * d400b5a: Return BC compatibility for `@Route` parameters and default values

* 2.2.6 (2013-08-26)

 * f936b41: clearToken exception is thrown at wrong place.
 * d0faf55: [Locale] Fixed: StubLocale::setDefault() throws no exception when "en" is passed
 * 566d79c: [Yaml] fixed embedded folded string parsing
 * 0951b8d: [Translation] Fixed regression: When only one rule is passed to transChoice(), this rule should be used
 * 4563f1b: [Yaml] Fix comment containing a colon on a scalar line being parsed as a hash.
 * 7e87eb1: fixed request format when forwarding a request
 * ccaaedf: [Form] PropertyPathMapper::mapDataToForms() *always* calls setData() on every child to ensure that all *_DATA events were fired when the initialization phase is over (except for virtual forms)
 * 00bc270: [Form] Fixed: submit() reacts to dynamic modifications of the form children
 * 05fdb12: Fixed issue #6932 - Inconsistent locale handling in subrequests
 * b3c3159: fixed locale of sub-requests when explicitely set by the developer (refs #8821)
 * b72bc0b: [Locale] fixed build-data exit code in case of an error
 * 9bb7a3d: fixed request format of sub-requests when explicitely set by the developer (closes #8787)
 * fa35597: Sets _format attribute only if it wasn't set previously by the user.
 * f946108: fixed the format of the request used to render an exception
 * 51022c3: Fix typo in the check_path validator
 * 5f7219e: added a missing use statement (closes #8808)
 * 262879d: fix for Process:isSuccessful()
 * 0723c10: [Process] Use a consistent way to reset data of the process latest run
 * 85a9c9d: [HttpFoundation] Fixed removing a nonexisting namespaced attribute.
 * 191d320: [Validation] Fixed IdentityTranslator to pass correct Locale to MessageSelector
 * c6ecd83: SwiftMailerHandler in Monolog bridge now able to react to kernel.terminate event
 * 99adcf1: {HttpFoundation] [Session] fixed session compatibility with memcached/redis session storage
 * ab9a96b: Fixes for hasParameterOption and getParameterOption methods of ArgvInput
 * dbd0855: Added sleep() workaround for windows php rename bug
 * fa769a2: [Process] Add more precision to Process::stop timeout
 * 3ef517b: [Process] Fix #8739
 * 18896d5a: [Validator] fixed the wrong isAbstract() check against the class (fixed #8589)
 * e8e76ec: [TwigBridge] Prevent code extension to display warning
 * 1a73b44: added missing support for the new output API in PHP 5.4+
 * e0c7d3d: Fixed bug introduced in #8675
 * 0b965fb: made the filesystem loader compatible with Twig 2.0
 * 322f880: replaced deprecated Twig features

* 2.2.5 (2013-08-07)

 * c35cc5b: added trusted hosts check
 * 6d555bc: Fixed metadata serialization
 * cd51d82: [Form] fixed wrong call to setTimeZone() (closes #8644)
 * 5c359a8: Fix issue with \DateTimeZone::UTC / 'UTC' for PHP 5.4
 * 97cbb19: [Form] Removed the "disabled" attribute from the placeholder option in select fields due to problems with the BlackBerry 10 browser
 * c138304: [routing] added ability for apache matcher to handle array values
 * b41cf82: [Validator] fixed StaticMethodLoader trying to invoke methods of abstract classes (closes #8589)
 * 3553c71: return 0 if there is no valid data
 * ae7fa11: [Twig] fixed TwigEngine::exists() method when a template contains a syntax error (closes #8546)
 * 28e0709: [Validator] fixed ConstraintViolation:: incorrect when nested
 * 890934d: handle Optional and Required constraints from XML or YAML sources correctly
 * a2eca45: Fixed #8455: PhpExecutableFinder::find() does not always return the correct binary
 * 485d53a: [DependencyInjection] Fix Container::camelize to convert beginning and ending chars
 * 2317443: [Security] fixed issue where authentication listeners clear unrelated tokens
 * 2ebb783: fix issue #8499 modelChoiceList call getPrimaryKey on a non object
 * d3eb9b7: [Validator] Fixed groups argument misplace for validateValue method from validator class

* 2.2.4 (2013-07-15)

 * 52e530d: Fixed NativeSessionStorage:regenerate when does not exists
 * bb59f40: Reverts JSON_NUMERIC_CHECK
 * 9c5f8c6: [Yaml] removed wrong comment removal inside a string block
 * 2dc1ee0: [HtppKernel] fixed inline fragment renderer
 * 06b69b8: fixed inline fragment renderer
 * 91bb757: ProgressHelper shows percentage complete.
 * 9d1004b: fix handling of a default 'template' as a string
 * 82dbaee: [HttpKernel] fixed the inline renderer when passing objects as attributes (closes #7124)
 * 6dbd1e1: [WebProfiler] fix content-type parameter
 * a830001: Passed the config when building the Configuration in ConfigurableExtension
 * c875d0a: [Form] fixed INF usage which does not work on Solaris (closes #8246)

* 2.2.3 (2013-06-19)

 * c0da3ae: [Process] Disable exception on stream_select timeout
 * 77f2aa8: [HttpFoundation] fixed issue with session_regenerate_id (closes #7380)
 * bcbbb28: Throw exception if value is passed to VALUE_NONE input, long syntax
 * 6b71513: fixed date type format pattern regex
 * 842f3fa: do not re-register commands each time a Console\Application is run
 * 0991cd0: [Process] moved env check to the Process class (refs #8227)
 * 8764944: fix issue where $_ENV contains array vals
 * 4139936: [DomCrawler] Fix handling file:// without a host
 * de289d2: [Form] corrected interface bind() method defined against in deprecation notice
 * 0c0a3e9: [Console] fixed regression when calling a command foo:bar if there is another one like foo:bar:baz (closes #8245)
 * 849f3ed: [Finder] Fix SplFileInfo::getContents isn't working with ssh2 protocol
 * 25e3abd: fix many-to-many Propel1 ModelChoiceList
 * bce6bd2: [DomCrawler] Fixed a fatal error when setting a value in a malformed field name.
 * 445b2e3: [Console] fix status code when Exception::getCode returns something like 0.1
 * bbfde62: Fixed exit code for exceptions with error code 0
 * afad9c7: instantiate valid commands only
 * 6d2135b: force the Content-Type to html in the web profiler controllers

* 2.2.2 (2013-06-02)

 * 2038329: [Form] [Validator] Fixed post_max_size = 0 bug (Issue #8065)
 * 169c0b9: [Finder] Fix iteration fails with non-rewindable streams
 * 45b68e0: [Finder] Fix unexpected duplicate sub path related AppendIterator issue
 * 5321600: Fixed two bugs in HttpCache
 * 5c317b7: [Console] fix and refactor exit code handling
 * 1469953: [CssSelector] Fix :nth-last-child() translation
 * 91b8490: Fix Crawler::children() to not trigger a notice for childless node
 * 0a4837d: Fixed XML syntax.
 * a5441b2: Fixed parsing of leading blank lines in folded scalars. Closes #7989.
 * ef87ba7: [Form] Fixed a method name.
 * e8d5d16: Fixed Loader import
 * 60edc58: Fixed fatal error in normalize/denormalizeObject.
 * 05b987f: [Process] Cleanup tests & prevent assertion that kills randomly Travis-CI
 * e4913f8: [Filesystem] Fix regression introduced in 10dea948
 * 5b7e1e6: added a missing check for the provider key
 * b0e3ea5: [Validator] fixed wrong URL for XSD
 * 59b78c7: [Validator] Fixed: $traverse and $deep is passed to the visitor from Validator::validate()
 * bcb5400: [Form] Fixed transform()/reverseTransform() to always throw TransformationFailedExceptions
 * 7b2ebbf: [Form] Fixed: String validation groups are never interpreted as callbacks
 * 0610750: if the repository method returns an array ensure that it's internal poin...
 * dcced01: [Form] Improved multi-byte handling of NumberToLocalizedStringTransformer
 * 2b554d7: remove validation related headers when needed
 * 2a531d7: Fix getPort() returning 80 instead of 443 when X-FORWARDED-PROTO is set to https
 * 10dea94: [Filesystem] copy() is not working when open_basedir is set
 * 8757ad4: [Process] Fix #5594 : `termsig` must be used instead of `stopsig` in exceptions when a process is signaled
 * be34917: [Console] find command even if its name is a namespace too (closes #7860)
 * 3c97004: Reset all catalogues when adding resource to fallback locale (#7715, #7819)
 * 0fb35a4: Added reloading of fallback catalogues when calling addResource() (#7715)
 * 9e49bc8: Re-added context information to log list
 * 06e21ff: Filesystem::touch() not working with different owners (utime/atime issue)
 * d98118a: [Config] #7644 add tests for passing number looking attributes as strings
 * 36d057b: [HttpFoundation][BrowserKit] fixed path when converting a cookie to a string
 * 495d0e3: [HttpFoundation] fixed empty domain= in Cookie::__toString()
 * c2bc707: fixed detection of secure cookies received over https
 * af819a7: [2.2] Pass ESI header to subrequests
 * 54bcf5c: [Translator] added additional conversion for encodings other than utf-8
 * 67b5797: fixed source messages to accept pluralized messages [Validator][translation][japanese] add messages for new validator
 * 8a434ed: fix a DI circular reference recognition bug
 * 22bf965: [DependencyInjection] fixed wrong exception class
 * 5abf887: Fix default value handling for multi-value options
 * da156d3: fix overwriting of request's locale if attribute _locale is missing
 * 1adbe3c: [HttpKernel] truncate profiler token to 6 chars (see #7665)
 * d552e4c: [HttpFoundation] do not use server variable PATH_INFO because it is already decoded and thus symfony is fragile to double encoding of the path
 * 4c51ec7: Fix download over SSL using IE < 8 and binary file response
 * 46909fa: [Console] Fix merging of application definition, fixes #7068, replaces #7158
 * 972bde7: [HttpKernel] fixed the Kernel when the ClassLoader component is not available (closes #7406)
 * f163226: fixed output of bag values
 * 047212a: [Yaml] fixed handling an empty value
 * 94a9cdc: [Routing][XML Loader] Add a possibility to set a default value to null
 * 302d44f: [Console] fixed handling of "0" input on ask
 * 383a84b: fixed handling of "0" input on ask
 * 0f0c29c: [HttpFoundation] Fixed bug in key searching for NamespacedAttributeBag
 * 7fc429f: [Form] DateTimeToRfc3339Transformer use proper transformation exteption in reverse transformation
 * 9fcd2f6: [HttpFoundation] fixed the creation of sub-requests under some circumstances for IIS
 * 8a9e898: Fix finding ACLs from ObjectIdentity's with different types
 * a3826ab: #7531: [HttpKernel][Config] FileLocator adds NULL as global resource path
 * 9d71ebe: Fix autocompletion of command names when namespaces conflict
 * bec8ff1: Fix timeout in Process::stop method
 * 3780fdb: Fix Process timeout
 * 99256e4: [HttpKernel] Remove args from 5.3 stack traces to avoid filling log files, fixes #7259
 * e8cae94: fix overwriting of request's locale if attribute _locale is missing
 * c4da2d9: [HttpFoundation] getClientIp is fixed.

* 2.2.1 (2013-04-06)

 * 751abe1: Doctrine cannot handle bare random non-utf8 strings
 * 673fd9b: idAsIndex should be true with a smallint or bigint id field.
 * 64a1d39: Fixed long multibyte parameter logging in DbalLogger:startQuery
 * 4cf06c1: Keep the file extension in the temporary copy and test that it exists (closes #7482)
 * 64ac34d: [Security] fixed wrong interface
 * 9875c4b: Added '@@' escaping strategy for YamlFileLoader and YamlDumper
 * bbcdfe2: [Yaml] fixed bugs with folded scalar parsing
 * 5afea04: [Form] made DefaultCsrfProvider using session_status() when available
 * c928ddc: [HttpFoudantion] fixed Request::getPreferredLanguage()
 * e6b7515: [DomCrawler] added support for query string with slash
 * 633c051: Fixed invalid file path for hiddeninput.exe on Windows.
 * 7ef90d2: fix xsd definition for strict-requirements
 * 39445c5: [WebProfilerBundle] Fixed the toolbar styles to apply them in IE8
 * 601da45: [ClassLoader] fixed heredocs handling
 * 17dc2ff: [HttpRequest] fixes Request::getLanguages() bug
 * 67fbbac: [DoctrineBridge] Fixed non-utf-8 recognition
 * e51432a: sub-requests are now created with the same class as their parent
 * cc3a40e: [FrameworkBundle] changed temp kernel name in cache:clear
 * d7a7434: [Routing] fix url generation for optional parameter having a null value
 * ef53456: [DoctrineBridge] Avoids blob values to be logged by doctrine
 * 6575df6: [Security] use current request attributes to generate redirect url?
 * 7216cb0: [Validator] fix showing wrong max file size for upload errors
 * c423f16: [2.1][TwigBridge] Fixes Issue #7342 in TwigBridge
 * 7d87ecd: [FrameworkBundle] fixed cache:clear command's warmup
 * 5ad4bd1: [TwigBridge] now enter/leave scope on Twig_Node_Module
 * fe4cc24: [TwigBridge] fixed fixed scope & trans_default_domain node visitor
 * fc47589: [BrowserKit] added ability to ignored malformed set-cookie header
 * 602cdee: replace INF to PHP_INT_MAX inside Finder component.
 * 5bc30bb: [Translation] added xliff loader/dumper with resname support
 * 663c796: Property accessor custom array object fix
 * 4f3771d: [2.2][HttpKernel] fixed wrong option name in FragmentHandler::fixOptions
 * a735cbd: fix xargs pipe to work with spaces in dir names
 * 15bf033: [FrameworkBundle] fix router debug command
 * d16d193: [FramworkBundle] removed unused property of trans update command
 * 523ef29: Fix warning for buildXml method
 * 7241be9: [Finder] fixed a potential issue on Solaris where INF value is wrong (refs #7269)
 * 1d3da29: [FrameworkBundle] avoids cache:clear to break if new/old folders already exist
 * b9cdb9a: [HttpKernel] Fixed possible profiler token collision (closes #7272, closes #7171)
 * d1f5d25: [FrameworkBundle] Fixes invalid serialized objects in cache
 * c82c754: RedisProfilerStorage wrong db-number/index-number selected
 * e86fefa: Unset loading[$id] in ContainerBuilder on exception
 * 709518b: Default validation message translation fix.
 * c0687cd: remove() should not use deprecated getParent() so it does not trigger deprecation internally
 * 708c0d3: adjust routing tests to not use prefix in addCollection
 * acff735: [Routing] trigger deprecation warning for deprecated features that will be removed in 2.3
 * 41ad9d8: [Routing] make xml loader more tolerant
 * 73bead7: [ClassLoader] made DebugClassLoader idempotent
 * a4ec677: [DomCrawler] Fix relative path handling in links
 * 6681df0: [Console] fixed StringInput binding
 * 5bf2f71: [Console] added deprecation annotation
 * 8d9cd42: Routing issue with installation in a sub-directory ref: https://github.com/symfony/symfony/issues/7129
 * c97ee8d: [Translator] mention that the message id may also be an object that can be cast to string in TranslatorInterface and fix the IdentityTranslator that did not respect this
 * 5a36b2d: [Translator] fix MessageCatalogueInterface::getFallbackCatalogue that can return null

* 2.2.0 (2013-03-01)

 * 5b19c89: [Console] fixed unparsed StringInput tokens
 * e92b76c: Mask PHP_AUTH_PW header in profiler
 * bae83c7: [TwigBridge] fixed trans twig extractor
 * f40adbc: [Finder] adds adapter selection/unselection capabilities
 * 8f8ba38: [DomCrawler] fix handling of schemes by Link::getUri()
 * 83382bc: [TwigBridge] fixed the translator extractor that were not trimming the text in trans tags (closes #7056)
 * b1ea8e5: Fixed handling absent href attribute in base tag
 * 83a61cf: fixed paths/notPaths regex for shell adapters
 * 32c5bf7: fix issue 4911
 * 13b8ce0: Adds expandable globs support to shell adapters
 * 850bd5a: [HttpFoundation] Fixed messed up headers
 * 4ecc246: Fixes AppCache + ESI + Stopwatch problem
 * 0690709: added a DebuClassLoader::findFile() method to make the wrapping less invasive
 * da22926: [Validator] gracefully handle transChoice errors
 * 635b1fc: StringInput resets the given options

* 2.2.0-RC3 (2013-02-24)

 * b2080c4: [HttpFoundation] Remove Cache-Control when using https download via IE<9 (fixes #6750)
 * b7bd630: [Form] Fixed TimeType not to render a "size" attribute in select tags
 * 368f62f: Expanded fault-tolerance for unusual cookie dates
 * 171cff0: [FrameworkBundle] Fix a BC for Hinclude global template
 * 3e40c17: [HttpKernel] fixed locale management when exiting sub-requests
 * 3933912: fixed HInclude renderer (closes #7113)
 * 189fba6: Removed some leaking deprecation warning in the Form component
 * d0e4b76: [HttpFoundation] fixed, overwritten CONTENT_TYPE
 * 609636e: [Config] tweaked dumper to indent multi-line info
 * 0eff68f: Fix REMOTE_ADDR for cached subrequests
 * 54d7d25: [HttpKernel] hinclude fragment renderer must escape URIs properly to return valid html
 * f842ae6: [FrameworkBundle] CSRF should be on by default
 * cb319ac: [HttpKernel] added error display suppression when using the ErrorHandler (if not, errors are displayed twice, refs #6254)
 * de0f7b7: [HttpFoundation] Added getter for httpMethodParameterOverride state
