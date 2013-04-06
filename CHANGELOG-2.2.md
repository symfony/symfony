CHANGELOG for 2.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.2 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.2.0...v2.2.1

* 2.2.1 (2013-04-06)

 * 751abe1: Doctrine cannot handle bare random non-utf8 strings
 * 673fd9b:  idAsIndex should be true with a smallint or bigint id field.
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
 * 7d87ecd: [FrameworkBundle] fixed cahe:clear command's warmup
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
