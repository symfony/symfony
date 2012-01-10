CHANGELOG for 2.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.0 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.0.0...v2.0.1

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
