CHANGELOG for 2.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.2.0...v2.2.1

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
