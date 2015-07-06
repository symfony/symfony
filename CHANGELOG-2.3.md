CHANGELOG for 2.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.3 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.3.0...v2.3.1

* 2.3.30 (2015-05-30)

 * bug #14262 [REVERTED] [TwigBundle] Refresh twig paths when resources change. (aitboudad)

* 2.3.29 (2015-05-26)

 * security #14759 CVE-2015-4050 [HttpKernel] Do not call the FragmentListener if _controller is already defined (jakzal)
 * bug #14715 [Form] Check instance of FormBuilderInterface instead of FormBuilder (dosten)
 * bug #14678 [Security] AbstractRememberMeServices::encodeCookie() validates cookie parts (MacDada)
 * bug #14635 [HttpKernel] Handle an array vary header in the http cache store (jakzal)
 * bug #14513 [console][formater] allow format toString object. (aitboudad)
 * bug #14335 [HttpFoundation] Fix baseUrl when script filename is contained in pathInfo (danez)
 * bug #14593 [Security][Firewall] Avoid redirection to XHR URIs (asiragusa)
 * bug #14618 [DomCrawler] Throw an exception if a form field path is incomplete (jakzal)
 * bug #14698  Fix HTML escaping of to-source links (nicolas-grekas)
 * bug #14690 [HttpFoundation] IpUtils::checkIp4() should allow `/0` networks (zerkms)
 * bug #14262 [TwigBundle] Refresh twig paths when resources change. (aitboudad)
 * bug #13633 [ServerBag] Handled bearer authorization header in REDIRECT_ form (Lance0312)
 * bug #13637 [CSS] WebProfiler break words (nicovak)
 * bug #14633 [EventDispatcher] make listeners removable from an executed listener (xabbuh)

* 2.3.28 (2015-05-10)

 * bug #14266 [HttpKernel] Check if "symfony/proxy-manager-bridge" package is installed (hason)
 * bug #14501 [ProxyBridge] Fix proxy classnames generation (xphere)
 * bug #14498 [FrameworkBundle] Added missing log in server:run command (lyrixx)
 * bug #14484 [SecurityBundle][WebProfiler] check authenticated user by tokenClass instead of username. (aitboudad)
 * bug #14497 [HttpFoundation] Allow curly braces in trusted host patterns (sgrodzicki)
 * bug #14436 Show a better error when the port is in use (dosten)
 * bug #14463 [Validator] Fixed Choice when an empty array is used in the "choices" option (webmozart)
 * bug #14402 [FrameworkBundle][Translation] Check for 'xlf' instead of 'xliff' (xelaris)
 * bug #14272 [FrameworkBundle] Workaround php -S ignoring auto_prepend_file (nicolas-grekas)
 * bug #14345 [FrameworkBundle] Fix Routing\DelegatingLoader resiliency to fatal errors (nicolas-grekas)
 * bug #14325 [Routing][DependencyInjection] Support .yaml extension in YAML loaders (thunderer)
 * bug #14344 [Translation][fixed test] refresh cache when resources are no longer fresh. (aitboudad)
 * bug #14268 [Translator] Cache does not take fallback locales into consideration (sf2.3) (mpdude)
 * bug #14192 [HttpKernel] Embed the original exception as previous to bounced exceptions (nicolas-grekas)
 * bug #14102 [Enhancement] netbeans - force interactive shell when limited detection (cordoval)
 * bug #14191 [StringUtil] Fixed singularification of 'movies' (GerbenWijnja)

* 2.3.27 (2015-04-01)

 * security #14167 CVE-2015-2308 (nicolas-grekas)
 * security #14166 CVE-2015-2309 (neclimdul)
 * bug #14010 Replace GET parameters when changed in form (WouterJ)
 * bug #13991 [Dependency Injection] Improve PhpDumper Performance for huge Containers (BattleRattle)
 * bug #13997 [2.3+][Form][DoctrineBridge] Improved loading of entities and documents (guilhermeblanco)
 * bug #13953 [Translation][MoFileLoader] fixed load empty translation. (aitboudad)
 * bug #13912 [DependencyInjection] Highest precedence for user parameters (lyrixx)

* 2.3.26 (2015-03-17)

 * bug #13927 Fixing wrong variable name from #13519 (weaverryan)
 * bug #13519 [DependencyInjection] fixed service resolution for factories (fabpot)
 * bug #13901 [Bundle] Fix charset config (nicolas-grekas, bamarni)
 * bug #13911 [HttpFoundation] MongoDbSessionHandler::read() now checks for valid session age (bzikarsky)
 * bug #13890 Fix XSS in Debug exception handler (fabpot)
 * bug #13744 minor #13377 [Console] Change greater by greater or equal for isFresh in FileResource (bijibox)
 * bug #13708  [HttpFoundation] fixed param order for Nginx's x-accel-mapping (phansys)
 * bug #13767 [HttpKernel] Throw double-bounce exceptions (nicolas-grekas)
 * bug #13769 [Form] NativeRequestHandler file handling fix (mpajunen)
 * bug #13779 [FrameworkBundle] silence E_USER_DEPRECATED in insulated clients (nicolas-grekas)
 * bug #13715 Enforce UTF-8 charset for core controllers (WouterJ)
 * bug #13683 [PROCESS] make sure /dev/tty is readable (staabm)
 * bug #13733 [Process] Fixed PhpProcess::getCommandLine() result (francisbesset)
 * bug #13618 [PropertyAccess] Fixed invalid feedback -> foodback singularization (WouterJ)
 * bug #13630 [Console] fixed ArrayInput, if array contains 0 key. (arima-ryunosuke)
 * bug #13647 [FrameworkBundle] Fix title and placeholder rendering in php form templates (jakzal)
 * bug #13607 [Console] Fixed output bug, if escaped string in a formatted string. (tronsha)
 * bug #13466 [Security] Remove ContextListener's onKernelResponse listener as it is used (davedevelopment)
 * bug #12864 [Console][Table] Fix cell padding with multi-byte (ttsuruoka)
 * bug #13375 [YAML] Fix one-liners to work with multiple new lines (Alex Pott)
 * bug #13545 fixxed order of usage (OskarStark)
 * bug #13567 [Routing] make host matching case-insensitive (Tobion)

* 2.3.25 (2015-01-30)

 * bug #13528 [Validator] reject ill-formed strings (nicolas-grekas)
 * bug #13525 [Validator] UniqueEntityValidator - invalidValue fixed. (Dawid Sajdak)
 * bug #13527 [Validator] drop grapheme_strlen in LengthValidator (nicolas-grekas)
 * bug #13376 [FrameworkBundle][config] allow multiple fallback locales. (aitboudad)
 * bug #12972 Make the container considered non-fresh if the environment parameters are changed (thewilkybarkid)
 * bug #13309 [Console] fixed 10531 (nacmartin)
 * bug #13352 [Yaml] fixed parse shortcut Key after unindented collection. (aitboudad)
 * bug #13039 [HttpFoundation] [Request] fix baseUrl parsing to fix wrong path_info (rk3rn3r)
 * bug #13250 [Twig][Bridge][TranslationDefaultDomain] add support of named arguments. (aitboudad)
 * bug #13332 [Console] ArgvInput and empty tokens (Taluu)
 * bug #13293 [EventDispatcher] Add missing checks to RegisterListenersPass (znerol)
 * bug #13262 [Yaml] Improve YAML boolean escaping (petert82, larowlan)
 * bug #13420 [Debug] fix loading order for legacy classes (nicolas-grekas)
 * bug #13371 fix missing comma in YamlDumper (garak)
 * bug #13365 [HttpFoundation] Make use of isEmpty() method (xelaris)
 * bug #13347 [Console] Helper\TableHelper->addRow optimization (boekkooi)
 * bug #13346 [PropertyAccessor] Allow null value for a array (2.3) (boekkooi)
 * bug #13170 [Form] Set a child type to text if added to the form without a type. (jakzal)
 * bug #13334 [Yaml] Fixed #10597: Improved Yaml directive parsing (VictoriaQ)

* 2.3.24 (2015-01-07)

 * bug #13286 [Security] Don't destroy the session on buggy php releases. (derrabus)
 * bug #12417 [HttpFoundation] Fix an issue caused by php's Bug #66606. (wusuopu)
 * bug #13200 Don't add Accept-Range header on unsafe HTTP requests (jaytaph)
 * bug #12491 [Security] Don't send remember cookie for sub request (blanchonvincent)
 * bug #12574 [HttpKernel] Fix UriSigner::check when _hash is not at the end of the uri (nyroDev)
 * bug #13185 Fixes Issue #13184 - incremental output getters now return empty strings (Bailey Parker)
 * bug #13145 [DomCrawler] Fix behaviour with <base> tag (dkop, WouterJ)
 * bug #13141 [TwigBundle] Moved the setting of the default escaping strategy from the Twig engine to the Twig environment (fabpot)
 * bug #13114 [HttpFoundation] fixed error when an IP in the X-Forwarded-For HTTP head... (fabpot)
 * bug #12572 [HttpFoundation] fix checkip6 (Neime)
 * bug #13075 [Config] fix error handler restoration in test (nicolas-grekas)
 * bug #13081 [FrameworkBundle] forward error reporting level to insulated Client (nicolas-grekas)
 * bug #13053 [FrameworkBundle] Fixed Translation loader and update translation command. (saro0h)
 * bug #13048 [Security] Delete old session on auth strategy migrate (xelaris)
 * bug #12999 [FrameworkBundle] fix cache:clear command (nicolas-grekas)
 * bug #13004 add a limit and a test to FlattenExceptionTest. (Daniel Wehner)
 * bug #12961 fix session restart on PHP 5.3 (Tobion)
 * bug #12761 [Filesystem] symlink use RealPath instead LinkTarget (aitboudad)
 * bug #12855 [DependencyInjection] Perf php dumper (nicolas-grekas)
 * bug #12894 [FrameworkBundle][Template name] avoid  error message for the shortcut n... (aitboudad)
 * bug #12858 [ClassLoader] Fix undefined index in ClassCollectionLoader (szicsu)

* 2.3.23 (2014-12-03)

 * bug #12811 Configure firewall's kernel exception listener with configured entry point or a default entry point (rjkip)
 * bug #12784 [DependencyInjection] make paths relative to __DIR__ in the generated container (nicolas-grekas)
 * bug #12716 [ClassLoader] define constant only if it wasn't defined before (xabbuh)
 * bug #12553 [Debug] fix error message on double exception (nicolas-grekas)
 * bug #12550 [FrameworkBundle] backport #12489 (xabbuh)
 * bug #12570 Fix initialized() with aliased services (Daniel Wehner)
 * bug #12137 [FrameworkBundle] cache:clear command fills *.php.meta files with wrong data (Strate)

* 2.3.22 (2014-11-20)

 * bug #12525 [Bundle][FrameworkBundle] be smarter when guessing the document root (xabbuh)
 * bug #12296 [SecurityBundle] Authentication entry point is only registered with firewall exception listener, not with authentication listeners (rjkip)
 * bug #12393 [DependencyInjection] inlined factory not referenced (boekkooi)
 * bug #12436 [Filesystem] Fixed case for empty folder (yosmanyga)
 * bug #12370 [Yaml] improve error message for multiple documents (xabbuh)
 * bug #12170 [Form] fix form handling with OPTIONS request method (Tobion)
 * bug #12235 [Validator] Fixed Regex::getHtmlPattern() to work with complex and negated patterns (webmozart)
 * bug #12326 [Session] remove invalid hack in session regenerate (Tobion)
 * bug #12341 [Kernel] ensure session is saved before sending response (Tobion)
 * bug #12329 [Routing] serialize the compiled route to speed things up (Tobion)
 * bug #12316 Break infinite loop while resolving aliases (chx)
 * bug #12313 [Security][listener] change priority of switchuser (aitboudad)

* 2.3.21 (2014-10-24)

 * bug #11696 [Form] Fix #11694 - Enforce options value type check in some form types (kix)
 * bug #12209 [FrameworkBundle] Fixed ide links (hason)
 * bug #12208 Add missing argument (WouterJ)
 * bug #12197 [TwigBundle] do not pass a template reference to twig (Tobion)
 * bug #12196 [TwigBundle] show correct fallback exception template in debug mode (Tobion)
 * bug #12187 [CssSelector] don't raise warnings when exception is thrown (xabbuh)
 * bug #11998 [Intl] Integrated ICU data into Intl component #2 (webmozart)
 * bug #11920 [Intl] Integrated ICU data into Intl component #1 (webmozart)

* 2.3.20 (2014-09-28)

 * bug #9453 [Form][DateTime] Propagate invalid_message & invalid_message_parameters to date & time (egeloen)
 * bug #11058 [Security] bug #10242 Missing checkPreAuth from RememberMeAuthenticationProvider (glutamatt)
 * bug #12004 [Form] Fixed ValidatorTypeGuesser to guess properties without constraints not to be required (webmozart)
 * bug #11904 Make twig ExceptionController conformed with ExceptionListener (megazoll)
 * bug #11924 [Form] Moved POST_MAX_SIZE validation from FormValidator to request handler (rpg600, webmozart)
 * bug #11079 Response::isNotModified returns true when If-Modified-Since is later than Last-Modified (skolodyazhnyy)
 * bug #11989 [Finder][Urgent] Remove asterisk and question mark from folder name in test to prevent windows file system issues. (Adam)
 * bug #11908 [Translation] [Config] Clear libxml errors after parsing xliff file (pulzarraider)
 * bug #11937 [HttpKernel] Make sure HttpCache is a trusted proxy (thewilkybarkid)
 * bug #11970 [Finder] Escape location for regex searches (ymc-dabe)
 * bug #11837 Use getPathname() instead of string casting to get BinaryFileReponse file path (nervo)
 * bug #11513 [Translation] made XliffFileDumper support CDATA sections. (hhamon)
 * bug #11907 [Intl] Improved bundle reader implementations (webmozart)
 * bug #11874 [Console] guarded against non-traversable aliases (thierrymarianne)
 * bug #11799 [YAML] fix handling of empty sequence items (xabbuh)
 * bug #11906 [Intl] Fixed a few bugs in TextBundleWriter (webmozart)
 * bug #11459 [Form][Validator] All index items after children are to be considered grand-children when resolving ViolationPath (Andrew Moore)
 * bug #11715 [Form] FormBuilder::getIterator() now deals with resolved children (issei-m)
 * bug #11892 [SwiftmailerBridge] Bump allowed versions of swiftmailer (ymc-dabe)
 * bug #11918 [DependencyInjection] remove `service` parameter type from XSD (xabbuh)
 * bug #11905 [Intl] Removed non-working $fallback argument from ArrayAccessibleResourceBundle (webmozart)
 * bug #11497 Use separated function to resolve command and related arguments (JJK801)
 * bug #11374 [DI] Added safeguards against invalid config in the YamlFileLoader (stof)
 * bug #11897 [FrameworkBundle] Remove invalid markup (flack)
 * bug #11860 [Security] Fix usage of unexistent method in DoctrineAclCache. (mauchede)
 * bug #11850 [YAML] properly mask escape sequences in quoted strings (xabbuh)
 * bug #11856 [FrameworkBundle] backport more error information from 2.6 to 2.3 (xabbuh)
 * bug #11843 [Yaml] improve error message when detecting unquoted asterisks (xabbuh)

* 2.3.19 (2014-09-03)

 * security #11832 CVE-2014-6072 (fabpot)
 * security #11831 CVE-2014-5245 (stof)
 * security #11830 CVE-2014-4931 (aitboudad, Jérémy Derussé)
 * security #11829 CVE-2014-6061 (damz, fabpot)
 * security #11828 CVE-2014-5244 (nicolas-grekas, larowlan)
 * bug #10197 [FrameworkBundle] PhpExtractor bugfix and improvements (mtibben)
 * bug #11772 [Filesystem] Add FTP stream wrapper context option to enable overwrite (Damian Sromek)
 * bug #11788 [Yaml] fixed mapping keys containing a quoted # (hvt, fabpot)
 * bug #11160 [DoctrineBridge] Abstract Doctrine Subscribers with tags (merk)
 * bug #11768 [ClassLoader] Add a __call() method to XcacheClassLoader (tstoeckler)
 * bug #11726 [Filesystem Component] mkdir race condition fix #11626 (kcassam)
 * bug #11677 [YAML] resolve variables in inlined YAML (xabbuh)
 * bug #11639 [DependencyInjection] Fixed factory service not within the ServiceReferenceGraph. (boekkooi)
 * bug #11778 [Validator] Fixed wrong translations for Collection constraints (samicemalone)
 * bug #11756 [DependencyInjection] fix @return anno created by PhpDumper (jakubkulhan)
 * bug #11711 [DoctrineBridge] Fix empty parameter logging in the dbal logger (jakzal)
 * bug #11692 [DomCrawler] check for the correct field type (xabbuh)
 * bug #11672 [Routing] fix handling of nullable XML attributes (xabbuh)
 * bug #11624 [DomCrawler] fix the axes handling in a bc way (xabbuh)
 * bug #11676 [Form] Fixed #11675 ValueToDuplicatesTransformer accept "0" value (Nek-)
 * bug #11695 [Validators] Fixed failing tests requiring ICU 52.1 which are skipped otherwise (webmozart)
 * bug #11529 [WebProfilerBundle] Fixed double height of canvas (hason)
 * bug #11641 [WebProfilerBundle ] Fix toolbar vertical alignment (blaugueux)
 * bug #11559 [Validator] Convert objects to string in comparison validators (webmozart)
 * feature #11510 [HttpFoundation] MongoDbSessionHandler supports auto expiry via configurable expiry_field (catchamonkey)
 * bug #11408 [HttpFoundation] Update QUERY_STRING when overrideGlobals (yguedidi)
 * bug #11633 [FrameworkBundle] add missing attribute to XSD (xabbuh)
 * bug #11601 [Validator] Allow basic auth in url when using UrlValidator. (blaugueux)
 * bug #11609 [Console] fixed style creation when providing an unknown tag option (fabpot)
 * bug #10914 [HttpKernel] added an analyze of environment parameters for built-in server (mauchede)
 * bug #11598 [Finder] Shell escape and windows support (Gordon Franke, gimler)
 * bug #11499 [BrowserKit] Fixed relative redirects for ambiguous paths (pkruithof)
 * bug #11516 [BrowserKit] Fix browser kit redirect with ports (dakota)
 * bug #11545 [Bundle][FrameworkBundle] built-in server: exit when docroot does not exist (xabbuh)
 * bug #11560 Plural fix (1emming)
 * bug #11558 [DependencyInjection] Fixed missing 'factory-class' attribute in XmlDumper output (kerdany)
 * bug #11548 [Component][DomCrawler] fix axes handling in Crawler::filterXPath() (xabbuh)
 * bug #11422 [DependencyInjection] Self-referenced 'service_container' service breaks garbage collection (sun)
 * bug #11428 [Serializer] properly handle null data when denormalizing (xabbuh)
 * bug #10687 [Validator] Fixed string conversion in constraint violations (eagleoneraptor, webmozart)
 * bug #11475 [EventDispatcher] don't count empty listeners (xabbuh)
 * bug #11436 fix signal handling in wait() on calls to stop() (xabbuh, romainneutron)
 * bug #11469  [BrowserKit] Fixed server HTTP_HOST port uri conversion (bcremer, fabpot)
 * bug #11425 Fix issue described in #11421 (Ben, ben-rosio)
 * bug #11423 Pass a Scope instance instead of a scope name when cloning a container in the GrahpvizDumper (jakzal)
 * bug #11120 [Process] Reduce I/O load on Windows platform (romainneutron)
 * bug #11342 [Form] Check if IntlDateFormatter constructor returned a valid object before using it (romainneutron)
 * bug #11411 [Validator] Backported #11410 to 2.3: Object initializers are called only once per object (webmozart)
 * bug #11403 [Translator][FrameworkBundle] Added @ to the list of allowed chars in Translator (takeit)
 * bug #11381  [Process] Use correct test for empty string in UnixPipes (whs, romainneutron)

* 2.3.18 (2014-07-15)

 * [Security] Forced validate of locales passed to the translator
 * feature #11367 [HttpFoundation] Fix to prevent magic bytes injection in JSONP responses... (CVE-2014-4671) (Andrew Moore)
 * bug #11386 Remove Spaceless Blocks from Twig Form Templates (chrisguitarguy)
 * bug #9719 [TwigBundle] fix configuration tree for paths (mdavis1982, cordoval)
 * bug #11244 [HttpFoundation] Remove body-related headers when sending the response, if body is empty (SimonSimCity)

* 2.3.17 (2014-07-07)

 * bug #11238 [Translation] Added unescaping of ids in PoFileLoader (JustBlackBird)
 * bug #11194 [DomCrawler] Remove the query string and the anchor of the uri of a link (benja-M-1)
 * bug #11272 [Console] Make sure formatter is the same. (akimsko)
 * bug #11259 [Config] Fixed failed config schema loads due to libxml_disable_entity_loader usage (ccorliss)
 * bug #11234 [ClassLoader] fixed PHP warning on PHP 5.3 (fabpot)
 * bug #11179 [Process] Fix ExecutableFinder with open basedir (cs278)
 * bug #11242 [CssSelector] Refactored the CssSelector to remove the circular object graph (stof)
 * bug #11219 [DomCrawler] properly handle buttons with single and double quotes insid... (xabbuh)
 * bug #11220 [Components][Serializer] optional constructor arguments can be omitted during the denormalization process (xabbuh)
 * bug #11186 Added missing `break` statement (apfelbox)
 * bug #11169 [Console] Fixed notice in DialogHelper (florianv)
 * bug #11144 [HttpFoundation] Fixed Request::getPort returns incorrect value under IPv6 (kicken)
 * bug #10966 PHP Fatal error when getContainer method of ContainerAwareCommand has be... (kevinvergauwen)
 * bug #10981 [HttpFoundation] Fixed isSecure() check to be compliant with the docs (Jannik Zschiesche)
 * bug #11092 [HttpFoundation] Fix basic authentication in url with PHP-FPM (Kdecherf)
 * bug #10808 [DomCrawler] Empty select with attribute name="foo[]" bug fix (darles)
 * bug #11063 [HttpFoundation] fix switch statement (Tobion)
 * bug #11009 [HttpFoundation] smaller fixes for PdoSessionHandler (Tobion)
 * bug #11041 Remove undefined variable $e (skydiablo)

* 2.3.16 (2014-05-31)

 * bug #11014 [Validator] Remove property and method targets from the optional and required constraints (jakzal)
 * bug #10983 [DomCrawler] Fixed charset detection in html5 meta charset tag (77web)
 * bug #10979 Make rootPath part of regex greedy (artursvonda)
 * bug #10995 [TwigBridge][Trans]set %count% only on transChoice from the current context. (aitboudad)
 * bug #10987 [DomCrawler] Fixed a forgotten case of complex XPath queries (stof)

* 2.3.15 (2014-05-22)

 * reverted #10908

* 2.3.14 (2014-05-22)

 * bug #10849 [WIP][Finder] Fix wrong implementation on sortable callback comparator (ProPheT777)
 * bug #10929 [Process] Add validation on Process input (romainneutron)
 * bug #10958 [DomCrawler] Fixed filterXPath() chaining loosing the parent DOM nodes (stof, robbertkl)
 * bug #10953 [HttpKernel] fixed file uploads in functional tests without file selected (realmfoo)
 * bug #10937 [HttpKernel] Fix "absolute path" when we look to the cache directory (BenoitLeveque)
 * bug #10908 [HttpFoundation] implement session locking for PDO (Tobion)
 * bug #10894 [HttpKernel] removed absolute paths from the generated container (fabpot)
 * bug #10926 [DomCrawler] Fixed the initial state for options without value attribute (stof)
 * bug #10925 [DomCrawler] Fixed the handling of boolean attributes in ChoiceFormField (stof)
 * bug #10777 [Form] Automatically add step attribute to HTML5 time widgets to display seconds if needed (tucksaun)
 * bug #10909 [PropertyAccess] Fixed plurals for -ves words (csarrazi)
 * bug #10899 Explicitly define the encoding. (jakzal)
 * bug #10897 [Console] Fix a console test (jakzal)
 * bug #10896 [HttpKernel] Fixed cache behavior when TTL has expired and a default "global" TTL is defined (alquerci, fabpot)
 * bug #10841 [DomCrawler] Fixed image input case sensitive (geoffrey-brier)
 * bug #10714 [Console]Improve formatter for double-width character (denkiryokuhatsuden)
 * bug #10872 [Form] Fixed TrimListenerTest as of PHP 5.5 (webmozart)
 * bug #10762 [BrowserKit] Allow URLs that don't contain a path when creating a cookie from a string (thewilkybarkid)
 * bug #10863 [Security] Add check for supported attributes in AclVoter (artursvonda)
 * bug #10833 [TwigBridge][Transchoice] set %count% from the current context. (aitboudad)
 * bug #10820 [WebProfilerBundle] Fixed profiler seach/homepage with empty token (tucksaun)
 * bug #10815 Fixed issue #5427 (umpirsky)
 * bug #10817 [Debug] fix #10313: FlattenException not found (nicolas-grekas)
 * bug #10803 [Debug] fix ErrorHandlerTest when context is not an array (nicolas-grekas)
 * bug #10801 [Debug] ErrorHandler: remove $GLOBALS from context in PHP5.3 fix #10292 (nicolas-grekas)
 * bug #10797 [HttpFoundation] Allow File instance to be passed to BinaryFileResponse (anlutro)
 * bug #10643 [TwigBridge] Removed strict check when found variables inside a translation (goetas)

* 2.3.13 (2014-04-27)

 * bug #10789 [Console] Fixed the rendering of exceptions on HHVM with a terminal width (stof)
 * bug #10773 [WebProfilerBundle ] Fixed an edge case on WDT loading (tucksaun)
 * bug #10763 [Process] Disable TTY mode on Windows platform (romainneutron)
 * bug #10772 [Finder] Fix ignoring of unreadable dirs in the RecursiveDirectoryIterator (jakzal)
 * bug #10757 [Process] Setting STDIN while running should not be possible (romainneutron)
 * bug #10749 Fixed incompatibility of x509 auth with nginx (alcaeus)
 * bug #10735 [Translation] [PluralizationRules] Little correction for case 'ar' (klyk50)
 * bug #10720 [HttpFoundation] Fix DbalSessionHandler  (Tobion)
 * bug #10721 [HttpFoundation] status 201 is allowed to have a body (Tobion)
 * bug #10728 [Process] Fix #10681, process are failing on Windows Server 2003 (romainneutron)
 * bug #10733 [DomCrawler] Textarea value should default to empty string instead of null. (Berdir)
 * bug #10723 [Security] fix DBAL connection typehint (Tobion)
 * bug #10700 Fixes various inconsistencies in the code (fabpot)
 * bug #10697 [Translation] Make IcuDatFileLoader/IcuResFileLoader::load invalid resource compatible with HHVM. (idn2104)
 * bug #10652 [HttpFoundation] fix PDO session handler under high concurrency (Tobion)
 * bug #10669 [Profiler] Prevent throwing fatal errors when searching timestamps or invalid dates (stloyd)
 * bug #10670 [Templating] PhpEngine should propagate charset to its helpers (stloyd)
 * bug #10665 [DependencyInjection] Fix ticket #10663 - Added setCharset method call to PHP templating engine (koku)
 * bug #10654 Changed the typehint of the EsiFragmentRenderer to the interface (stof)
 * bug #10649 [BrowserKit] Fix #10641 : BrowserKit is broken when using ip as host (romainneutron)

* 2.3.12 (2014-04-03)

 * bug #10586 Fixes URL validator to accept single part urls (merk)
 * bug #10591 [Form] Buttons are now disabled if their containing form is disabled (webmozart)
 * bug #10579 HHVM fixes (fabpot)
 * bug #10564 fixed the profiler when an uncalled listener throws an exception when instantiated (fabpot)
 * bug #10568 [Form] Fixed hashing of choice lists containing non-UTF-8 characters (webmozart)
 * bug #10536 Avoid levenshtein comparison when using ContainerBuilder. (catch56)
 * bug #10549 Fixed server values in BrowserKit (fabpot)
 * bug #10540 [HttpKernel] made parsing controllers more robust (fabpot)
 * bug #10545 [DependencyInjection] Fixed YamlFileLoader imports path (jrnickell)
 * bug #10523 [Debug] Check headers sent before sending PHP response (GromNaN)
 * bug #10275 [Validator] Fixed ACE domain checks on UrlValidator (#10031) (aeoris)
 * bug #10123 handle array root element (greg0ire)
 * bug #10532 Fixed regression when using Symfony on filesystems without chmod support (fabpot)
 * bug #10502 [HttpKernel] Fix #10437: Catch exceptions when reloading a no-cache request (romainneutron)
 * bug #10493  Fix libxml_use_internal_errors and libxml_disable_entity_loader usage (romainneutron)
 * bug #9784 [HttpFoundation] Removed ini check to make Uploadedfile work on Google App Engine (micheleorselli)
 * bug #10416 [Form] Allow options to be grouped by objects (felds)
 * bug #10410 [Form] Fix "Array was modified outside object" in ResizeFormListener. (Chekote)
 * bug #10494 [Validator] Minor fix in IBAN validator (sprain)
 * bug #10491 Fixed bug that incorrectly causes the "required" attribute to be omitted from select even though it contains the "multiple" attribute (fabpot)
 * bug #10479 [Process] Fix escaping on Windows (romainneutron)
 * bug #10480 [Process] Fixed fatal errors in getOutput and getErrorOutput when process was not started  (romainneutron)
 * bug #10420 [Process] Make Process::start non-blocking on Windows platform (romainneutron)
 * bug #10455 [Process] Fix random failures in test suite on TravisCI (romainneutron)
 * bug #10448 [Process] Fix quoted arguments escaping (romainneutron)
 * bug #10444 [DomCrawler] Fixed incorrect value name conversion in getPhpValues() and getPhpFiles() (romainneutron)
 * bug #10423 [Config] XmlUtils::convertDomElementToArray does not handle '0' (bendavies)
 * bug #10153 [Process] Fixed data in pipe being truncated if not read before process termination (astephens25)
 * bug #10429 [Process] Fix #9160 : escaping an argument with a trailing backslash on windows fails (romainneutron)
 * bug #10412 [Process] Fix process status in TTY mode (romainneutron)
 * bug #10382 10158 get vary multiple (bbinkovitz)
 * bug #10251 [Form] Fixes empty file-inputs getting treated as extra field. (jenkoian)
 * bug #10351 [HttpKernel] fix stripComments() normalizing new-lines (sstok)
 * bug #10348 Update FileLoader to fix issue #10339 (msumme)

* 2.3.11 (2014-02-27)

 * bug #10146 [WebProfilerBundle] fixed parsing Mongo DSN and added Test for it (malarzm)
 * bug #10299 [Finder] () is also a valid delimiter (WouterJ)
 * bug #10255 [FrameworkBundle] Fixed wrong redirect url if path contains some query parameters (pulzarraider)
 * bug #10285 Bypass sigchild detection if phpinfo is not available (Seldaek)
 * bug #10269 [Form] Revert "Fix "Array was modified outside object" in ResizeFormListener." (norzechowicz)

* 2.3.10 (2014-02-12)

 * bug #10231 [Console] removed problematic regex (fabpot)
 * bug #10245 [DomCrawler] Added support for <area> tags to be treated as links (shamess)
 * bug #10232 [Form] Fix "Array was modified outside object" in ResizeFormListener. (Chekote)
 * bug #10215 [Routing] reduced recursion in dumper (arnaud-lb)
 * bug #10207 [DomCrawler] Fixed filterXPath() chaining (robbertkl)
 * bug #10205 [DomCrawler] Fixed incorrect handling of image inputs (robbertkl)
 * bug #10191 [HttpKernel] fixed wrong reference in TraceableEventDispatcher (fabpot)
 * bug #10195 [Debug] Fixed recursion level incrementing in FlattenException::flattenArgs(). (sun)
 * bug #10151 [Form] Update DateTime objects only if the actual value has changed (peterrehm)
 * bug #10140 allow the TextAreaFormField to be used with valid/invalid HTML (dawehner)
 * bug #10131 added lines to exceptions for the trans and transchoice tags (fabpot)
 * bug #10119 [Validator] Minor fix in XmlFileLoader (florianv)
 * bug #10078 [BrowserKit] add non-standard port to HTTP_HOST server param (kbond)
 * bug #10091 [Translation] Update PluralizationRules.php (guilhermeblanco)
 * bug #10053 [Form] fixed allow render 0 numeric input value (dczech)
 * bug #10033 [HttpKernel] Bugfix - Logger Deprecation Notice (Rican7)
 * bug #10023 [FrameworkBundle] Thrown an HttpException instead returning a Response in RedirectController::redirectAction() (jakzal)
 * bug #9985 Prevent WDT from creating a session (mvrhov)
 * bug #10000 [Console] Fixed the compatibility with HHVM (stof)
 * bug #9979 [Doctrine Bridge][Validator] Fix for null values in assosiated properties when using UniqueEntityValidator (vpetrovych)
 * bug #9983 [TwigBridge] Update min. version of Twig (stloyd)
 * bug #9970 [CssSelector] fixed numeric attribute issue (jfsimon)
 * bug #9747 [DoctrineBridge] Fix: Add type detection. Needed by pdo_dblib (iamluc)
 * bug #9962 [Process] Fix #9861 : Revert TTY mode (romainneutron)
 * bug #9960 [Form] Update minimal requirement in composer.json (stloyd)
 * bug #9952 [Translator] Fix Empty translations with Qt files (vlefort)
 * bug #9948 [WebProfilerBundle] Fixed profiler toolbar icons for XHTML. (rafalwrzeszcz)
 * bug #9933 Propel1 exception message (jaugustin)
 * bug #9949 [BrowserKit] Throw exception on invalid cookie expiration timestamp (anlutro)

* 2.3.9 (2014-01-05)

 * bug #9938 [Process] Add support SAPI cli-server (peter-gribanov)
 * bug #9940 [EventDispatcher] Fix hardcoded listenerTag name in error message (lemoinem)
 * bug #9908 [HttpFoundation] Throw proper exception when invalid data is passed to JsonResponse class (stloyd)
 * bug #9902 [Security] fixed pre/post authentication checks (fabpot)
 * bug #9899 [Filesystem | WCM] 9339 fix stat on url for filesystem copy (cordoval)
 * bug #9589 [DependencyInjection] Fixed #9020 - Added support for collections in service#parameters (lavoiesl)
 * bug #9889 [Console] fixed column width when using the Table helper with some decoration in cells (fabpot)
 * bug #9323 [DomCrawler]fix #9321 Crawler::addHtmlContent add gbk encoding support (bronze1man)
 * bug #8997 [Security] Fixed problem with losing ROLE_PREVIOUS_ADMIN role. (pawaclawczyk)
 * bug #9557 [DoctrineBridge] Fix for cache-key conflict when having a \Traversable as choices (DRvanR)
 * bug #9879 [Security] Fix ExceptionListener to catch correctly AccessDeniedException if is not first exception (fabpot)
 * bug #9885 [Dependencyinjection] Fixed handling of inlined references in the AnalyzeServiceReferencesPass (fabpot)
 * bug #9884 [DomCrawler] Fixed creating form objects from named form nodes (jakzal)
 * bug #9882 Add support for HHVM in the getting of the PHP executable (fabpot)
 * bug #9850 [Validator] Fixed IBAN validator with 0750447346 value (stewe)
 * bug #9865 [Validator] Fixes message value for objects (jongotlin)
 * bug #9441 [Form][DateTimeToArrayTransformer] Check for hour, minute & second validity (egeloen)
 * bug #9867 #9866 [Filesystem] Fixed mirror for symlinks (COil)
 * bug #9806 [Security] Fix parent serialization of user object (ddeboer)
 * bug #9834 [DependencyInjection] Fixed support for backslashes in service ids. (jakzal)
 * bug #9826 fix #9356 [Security] Logger should manipulate the user reloaded from provider (matthieuauger)
 * bug #9769 [BrowserKit] fixes #8311 CookieJar is totally ignorant of RFC 6265 edge cases (jzawadzki)
 * bug #9697 [Config] fix 5528 let ArrayNode::normalizeValue respect order of value array provided (cordoval)
 * bug #9701 [Config] fix #7243 allow 0 as arraynode name (cordoval)
 * bug #9795 [Form] Fixed issue in BaseDateTimeTransformer when invalid timezone cause Trans... (tyomo4ka)
 * bug #9714 [HttpFoundation] BinaryFileResponse should also return 416 or 200 on some range-requets (SimonSimCity)
 * bug #9601 [Routing] Remove usage of deprecated _scheme requirement (Danez)
 * bug #9489 [DependencyInjection] Add normalization to tag options (WouterJ)
 * bug #9135 [Form] [Validator] fix maxLength guesser (franek)
 * bug #9790 [Filesystem] Changed the mode for a target file in copy() to be write only (jakzal)

* 2.3.8 (2013-12-16)

 * bug #9758 [Console] fixed TableHelper when cell value has new line (k-przybyszewski)
 * bug #9760 [Routing] Fix router matching pattern against multiple hosts (karolsojko)
 * bug #9674 [Form] rename validators.ua.xlf to validators.uk.xlf (craue)
 * bug #9722 [Validator]Fixed getting wrong msg when value is an object in Exception (aitboudad)
 * bug #9750 allow TraceableEventDispatcher to reuse event instance in nested events (evillemez)
 * bug #9718 [validator] throw an exception if isn't an instance of ConstraintValidatorInterface. (aitboudad)
 * bug #9716 Reset the box model to content-box in the web debug toolbar (stof)
 * bug #9711 [FrameworkBundle] Allowed "0" as a checkbox value in php templates (jakzal)
 * bug #9665 [Bridge/Doctrine] ORMQueryBuilderLoader - handled the scenario when no entity manager is passed with closure query builder (jakzal)
 * bug #9656 [DoctrineBridge] normalized class names in the ORM type guesser (fabpot)
 * bug #9647 use the correct class name to retrieve mapped class' metadata and reposi... (xabbuh)
 * bug #9648 [Debug] ensured that a fatal PHP error is actually fatal after being handled by our error handler (fabpot)
 * bug #9643 [WebProfilerBundle] Fixed js escaping in time.html.twig (hason)
 * bug #9641 [Debug] Avoid notice from being "eaten" by fatal error. (fabpot)
 * bug #9639 Modified guessDefaultEscapingStrategy to not escape txt templates (fabpot)
 * bug #9314 [Form] Fix DateType for 32bits computers. (WedgeSama)
 * bug #9443 [FrameworkBundle] Fixed the registration of validation.xml file when the form is disabled (hason)
 * bug #9625 [HttpFoundation] Do not return an empty session id if the session was closed (Taluu)
 * bug #9637 [Validator] Replaced inexistent interface (jakzal)
 * bug #9605 Adjusting CacheClear Warmup method to namespaced kernels (rdohms)
 * bug #9610 Container::camelize also takes backslashes into consideration (ondrejmirtes)
 * bug #9447 [BrowserKit] fixed protocol-relative url redirection (jong99)
 * bug #9535 No Entity Manager defined exception (armetiz)
 * bug #9485 [Acl] Fix for issue #9433 (guilro)
 * bug #9516 [AclProvider] Fix incorrect behavior when partial results returned from cache (superdav42)
 * bug #9352 [Intl] make currency bundle merge fallback locales when accessing data, ... (shieldo)
 * bug #9537 [FrameworkBundle] Fix mistake in translation's service definition. (phpmike)
 * bug #9367 [Process] Check if the pipe array is empty before calling stream_select() (jfposton)
 * bug #9211 [Form] Fixed memory leak in FormValidator (bschussek)
 * bug #9469 [Propel1] re-factor Propel1 ModelChoiceList (havvg)

* 2.3.7 (2013-11-14)

 * bug #9499 Request::overrideGlobals() may call invalid ini value (denkiryokuhatsuden)
 * bug #9420 [Console][ProgressHelper] Fix ProgressHelper redraw when redrawFreq is greater than 1 (giosh94mhz)
 * bug #9212 [Validator] Force Luhn Validator to only work with strings (Richtermeister)
 * bug #9476 Fixed bug with lazy services (peterrehm)
 * bug #9431 [DependencyInjection] fixed YamlDumper did not make services private. (realityking)
 * bug #9416 fixed issue with clone now the children of the original form are preserved and the clone form is given new children (yjv)
 * bug #9412 [HttpFoundation] added content length header to BinaryFileResponse (kbond)
 * bug #9395 [HttpKernel] fixed memory limit display in MemoryDataCollector (hhamon)
 * bug #9388 [Form] Fixed: The "data" option is taken into account even if it is NULL (bschussek)
 * bug #9391 [Serializer] Fixed the error handling when decoding invalid XML to avoid a Warning (stof)
 * bug #9378 [DomCrawler] [HttpFoundation] Make `Content-Type` attributes identification case-insensitive (matthieuprat)
 * bug #9354 [Process] Fix #9343 : revert file handle usage on Windows platform (romainneutron)
 * bug #9334 [Form] Improved FormTypeCsrfExtension to use the type class as default intention if the form name is empty (bschussek)
 * bug #9333 [Form] Improved FormTypeCsrfExtension to use the type class as default intention if the form name is empty (bschussek)
 * bug #9338 [DoctrineBridge] Added type check to prevent calling clear() on arrays (bschussek)
 * bug #9328 [Form] Changed FormTypeCsrfExtension to use the form's name as default intention (bschussek)
 * bug #9327 [Form] Changed FormTypeCsrfExtension to use the form's name as default intention (bschussek)
 * bug #9308 [DoctrineBridge] Loosened CollectionToArrayTransformer::transform() to accept arrays (bschussek)
 * bug #9274 [Yaml] Fixed the escaping of strings starting with a dash when dumping (stof)
 * bug #9270 [Templating] Fix in ChainLoader.php (janschoenherr)
 * bug #9246 [Session] fixed wrong started state (tecbot)

* 2.3.6 (2013-10-10)

 * [Security] limited the password length passed to encoders
 * bug #9259 [Process] Fix latest merge from 2.2 in 2.3 (romainneutron)
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
 * bug #9153 [DependencyInjection] Prevented inlining of lazy loaded private service definitions (jakzal)
 * bug #9103 [HttpFoundation] Header `HTTP_X_FORWARDED_PROTO` can contain various values (stloyd)

* 2.3.5 (2013-09-27)

 * 8980954: bugix: CookieJar returns cookies with domain "domain.com" for domain "foodomain.com"
 * bb59ac2: fixed HTML5 form attribute handling XPath query
 * 3108c71: [Locale] added support for the position argument to NumberFormatter::parse()
 * 0774c79: [Locale] added some more stubs for the number formatter
 * e5282e8: [DomCrawler]Crawler guess charset from html
 * 0e80d88: fixes RequestDataCollector bug, visible when used on Drupal8
 * c8d0342: [Console] fixed exception rendering when nested styles
 * a47d663: [Console] fixed the formatter for single-char tags
 * c6c35b3: [Console] Escape exception message during the rendering of an exception
 * 04e730e: [DomCrawler] fixed HTML5 form attribute handling
 * 0e437c5: [BrowserKit] Fixed the handling of parameters when redirecting
 * d84df4c: [Process] Properly close pipes after a Process::stop call
 * b3ae29d: fixed bytes conversion when used on 32-bits systems
 * a273e79: [Form] Fixed: "required" attribute is not added to <select> tag if no empty value
 * 958ec09: NativeSessionStorage regenerate
 * 0d6af5c: Use setTimeZone if this method exists.
 * 42019f6: [Console] Fixed argument parsing when a single dash is passed.
 * 097b376: [WebProfilerBundle] fixed toolbar for IE8 (refs #8380)
 * 4f5b8f0: [HttpFoundation] tried to keep the original Request URI as much as possible to avoid different behavior between ::createFromGlobals() and ::create()
 * 4c1dbc7: [TwigBridge] fixed form rendering when used in a template with dynamic inheritance
 * 8444339: [HttpKernel] added a check for private event listeners/subscribers
 * 427ee19: [FrameworkBundle] fixed registration of the register listener pass
 * ce7de37: [DependencyInjection] fixed a non-detected circular reference in PhpDumper (closes #8425)
 * 37102dc: [Process] Close unix pipes before calling `proc_close` to avoid a deadlock
 * 8c2a733: [HttpFoundation] fixed format duplication in Request
 * 1e75cf9: [Process] Fix #8970 : read output once the process is finished, enable pipe tests on Windows
 * 9542d72: [Form] Fixed expanded choice field to be marked invalid when unknown choices are submitted
 * 72b8807: [Form] Fixed ChoiceList::get*By*() methods to preserve order and array keys
 * b65a515: [Form] Fixed FormValidator::findClickedButton() not to be called exponentially
 * 49f5027: [HttpKernel] fixer HInclude src (closes #8951)
 * c567262: Fixed escaping of service identifiers in configuration
 * 4a76c76: [Process][2.2] Fix Process component on windows
 * 65814ba: Request->getPort() should prefer HTTP_HOST over SERVER_PORT
 * e75d284: Fixing broken http auth digest in some circumstances (php-fpm + apache).
 * 970405f: fixed some circular references
 * 899f176: [Security] fixed a leak in ExceptionListener
 * 2fd8a7a: [Security] fixed a leak in the ContextListener
 * 6362fa4: Button missing getErrorsAsString() fixes #8084 Debug: Not calling undefined method anymore. If the form contained a submit button the call would fail and the debug of the form wasn't possible. Now it will work in all cases. This fixes #8084
 * e4b3039: Use isset() instead of array_key_exists() in DIC
 * 2d34e78: [BrowserKit] fixed method/files/content when redirecting a request
 * 64e1655: [BrowserKit] removed some headers when redirecting a request
 * 96a4b00: [BrowserKit] fixed headers when redirecting if history is set to false (refs #8697)
 * c931eb7: [HttpKernel] fixed route parameters storage in the Request data collector (closes #8867)
 * 96bb731: optimized circular reference checker
 * 39b610d: Clear lazy loading initializer after the service is successfully initialized
 * 91234cd: [HttpKernel] changed fragment URLs to be relative by default (closes #8458)
 * 4922a80: [FrameworkBundle] added support for double-quoted strings in the extractor (closes #8797)
 * 52d8676: [Intl] made RegionBundle and LanguageBundle merge fallback data when using a country-specific locale
 * 0d07af8: [BrowserKit] Pass headers when `followRedirect()` is called
 * d400b5a: Return BC compatibility for `@Route` parameters and default values

* 2.3.4 (2013-08-27)

 * f936b41: clearToken exception is thrown at wrong place.
 * ea480bd: [Form] Fixed Form::all() signature for PHP 5.3.3
 * e1f40f2: [Locale] Fixed: Locale::setDefault() throws no exception when "en" is passed
 * d0faf55: [Locale] Fixed: StubLocale::setDefault() throws no exception when "en" is passed
 * 566d79c: [Yaml] fixed embedded folded string parsing
 * 33b0a17: [Validator] fixed Boolean handling in XML constraint mappings (closes #5603)
 * 0951b8d: [Translation] Fixed regression: When only one rule is passed to transChoice(), this rule should be used
 * 4563f1b: [Yaml] Fix comment containing a colon on a scalar line being parsed as a hash.
 * 7e87eb1: fixed request format when forwarding a request
 * 07d14e5: [Form] Removed exception in Button::setData(): setData() is now always called for all elements in the form tree during the initialization of the tree
 * ccaaedf: [Form] PropertyPathMapper::mapDataToForms() *always* calls setData() on every child to ensure that all *_DATA events were fired when the initialization phase is over (except for virtual forms)
 * 00bc270: [Form] Fixed: submit() reacts to dynamic modifications of the form children
 * c4636e1: added a functional test for locale handling in sub-requests
 * 05fdb12: Fixed issue #6932 - Inconsistent locale handling in subrequests
 * b3c3159: fixed locale of sub-requests when explicitely set by the developer (refs #8821)
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
 * c342715: [Form] Fixed: Added "validation_groups" option to submit button
 * fa01e6b: [Process] Fix for #8754 (Timed-out processes are successful)
 * 909fab6: [Process] Fix #8742 : Signal-terminated processes are not successful
 * fa769a2: [Process] Add more precision to Process::stop timeout
 * 3ef517b: [Process] Fix #8739
 * 572ba68: [TwigBridge] removed superflous ; when rendering form_enctype() (closes #8660)
 * 18896d5a: [Validator] fixed the wrong isAbstract() check against the class (fixed #8589)
 * e8e76ec: [TwigBridge] Prevent code extension to display warning
 * 96aec0f: Fix internal sub-request creation
 * 6ed0fdf: [Form] Moved auto_initialize option to the BaseType
 * e47657d: Make sure ContextErrorException is loaded during compile time errors
 * 98f6969: Fix empty process argument escaping on Windows
 * 1a73b44: added missing support for the new output API in PHP 5.4+
 * e0c7d3d: Fixed bug introduced in #8675
 * 0b965fb: made the filesystem loader compatible with Twig 2.0
 * 8fa0453: [Intl] Updated stubs to reflect ICU 51.2
 * 322f880: replaced deprecated Twig features
 * 48338fc: Ignore null value in comparison validators

* 2.3.3 (2013-08-07)

 * c35cc5b: added trusted hosts check
 * 6d555bc: Fixed metadata serialization
 * cd51d82: [Form] fixed wrong call to setTimeZone() (closes #8644)
 * 5c359a8: Fix issue with \DateTimeZone::UTC / 'UTC' for PHP 5.4
 * 85330a6: [Form] Fixed patched forms to be valid even if children are not submitted
 * cb5e765: [Form] Fixed: If a form is not present in a request, it is not automatically submitted
 * 97cbb19: [Form] Removed the "disabled" attribute from the placeholder option in select fields due to problems with the BlackBerry 10 browser
 * c138304: [routing] added ability for apache matcher to handle array values
 * 1bd45b3: [FrameworkBundle] fixed regression where the command might have the wrong container if the application is reused several times
 * b41cf82: [Validator] fixed StaticMethodLoader trying to invoke methods of abstract classes (closes #8589)
 * e5fba3c: [Form] fixes empty file-inputs get treated as extra field
 * 3553c71: return 0 if there is no valid data
 * 50d0727: [DependencyInjection] fixed regression where setting a service to null did not trigger a re-creation of the service when getting it
 * dc1fff0: The ignoreAttributes itself should be ignored, too.
 * ae7fa11: [Twig] fixed TwigEngine::exists() method when a template contains a syntax error (closes #8546)
 * 28e0709: [Validator] fixed ConstraintViolation:: incorrect when nested
 * 890934d: handle Optional and Required constraints from XML or YAML sources correctly
 * a2eca45: Fixed #8455: PhpExecutableFinder::find() does not always return the correct binary
 * 485d53a: [DependencyInjection] Fix Container::camelize to convert beginning and ending chars
 * 2317443: [Security] fixed issue where authentication listeners clear unrelated tokens
 * 2ebb783: fix issue #8499 modelChoiceList call getPrimaryKey on a non object
 * 242b318: [DependencyInjection] Add exception for service name not dumpable in PHP
 * d3eb9b7: [Validator] Fixed groups argument misplace for validateValue method from validator class

* 2.3.2 (2013-07-17)

 * bb59f40: Reverts JSON_NUMERIC_CHECK
 * 9c5f8c6: [Yaml] removed wrong comment removal inside a string block
 * 2dc1ee0: [HtppKernel] fixed inline fragment renderer
 * 06b69b8: fixed inline fragment renderer
 * 91bb757: ProgressHelper shows percentage complete.
 * 9d1004b: fix handling of a default 'template' as a string
 * 82dbaee: [HttpKernel] fixed the inline renderer when passing objects as attributes (closes #7124)
 * 8bb4e4d: [DI] Fixed bug requesting non existing service from dumped frozen container
 * 6dbd1e1: [WebProfiler] fix content-type parameter
 * a830001: Passed the config when building the Configuration in ConfigurableExtension
 * c875d0a: [Form] fixed INF usage which does not work on Solaris (closes #8246)
 * ab1439e: [Console] Fixed the table rendering with multi-byte strings.
 * c0da3ae: [Process] Disable exception on stream_select timeout
 * 77f2aa8: [HttpFoundation] fixed issue with session_regenerate_id (closes #7380)
 * bcbbb28: Throw exception if value is passed to VALUE_NONE input, long syntax
 * 6b71513: fixed date type format pattern regex
 * b5ded81: [Security] fixed usage of the salt for the bcrypt encoder (refs #8210)
 * 842f3fa: do not re-register commands each time a Console\Application is run
 * 0991cd0: [Process] moved env check to the Process class (refs #8227)
 * 8764944: fix issue where $_ENV contains array vals
 * 4139936: [DomCrawler] Fix handling file:// without a host
 * e65723c: fix-progressbar-start
 * aa79393: also consider alias in Container::has()
 * de289d2: [Form] corrected interface bind() method defined against in deprecation notice
 * 0c0a3e9: [Console] fixed regression when calling a command foo:bar if there is another one like foo:bar:baz (closes #8245)
 * 849f3ed: [Finder] Fix SplFileInfo::getContents isn't working with ssh2 protocol
 * 6d2135b: force the Content-Type to html in the web profiler controllers

* 2.3.1 (2013-06-11)

 * 25e3abd: fix many-to-many Propel1 ModelChoiceList
 * bce6bd2: [DomCrawler] Fixed a fatal error when setting a value in a malformed field name.
 * e3561ce: [FrameworkBundle] Fixed OutOfBoundException when session handler_id is null
 * 81b122d: [DependencyInjection] Add support for aliases of aliases + regression test
 * 445b2e3: [Console] fix status code when Exception::getCode returns something like 0.1
 * bbfde62: Fixed exit code for exceptions with error code 0
 * d8c0ef7: [DependencyInjection] Rename ContainerBuilder::$aliases to avoid conflicting with the parent class
 * bb797ee: [DependencyInjection] Remove get*Alias*Service methods from compiled containers
 * 379f5e0: [DependencyInjection] Fix aliased access of shared services, fixes #8096
 * afad9c7: instantiate valid commands only

* 2.3.0 (2013-06-03)

 * e93fc7a: [FrameworkBundle] set the dispatcher in the console application
 * 2038329: [Form] [Validator] Fixed post_max_size = 0 bug (Issue #8065)
 * 554ab9f: [Console] renamed ConsoleForExceptionEvent into ConsoleExceptionEvent
 * fd151fd: [Security] Fixed the check if an interface exists.
 * c8e5503: [FrameworkBundle] removed HttpFoundation classes from HttpKernel cache
 * 169c0b9: [Finder] Fix iteration fails with non-rewindable streams
 * 45b68e0: [Finder] Fix unexpected duplicate sub path related AppendIterator issue
 * 13ba4ea: fix logger in regards to DebugLoggerInterface
 * 97b38ed: Added type of return value in VoterInterface.
 * 79a842a: [Console] Add namespace support back in to list command
 * 5321600: Fixed two bugs in HttpCache
 * 435012f: [Config] Adding the previous exception message into the FileLoaderLoadException so it's more easily seen
 * 5c317b7: [Console] fix and refactor exit code handling
 * 1469953: [CssSelector] Fix :nth-last-child() translation
 * 2d9027d: [CssSelector] Fix :nth-last-child() translation
 * 91b8490: Fix Crawler::children() to not trigger a notice for childless node

* 2.3.0-RC1 (2013-05-16)

 * 95f356b: remove check for PHP bug #50731
 * 8f54da7: [BrowserKit] should not follow redirects if status code is not 30x
 * f41ac06: changed all version deps to accepts all upcoming Symfony versions
 * a4e3ebf: [DomCrawler] Fixed the Crawler::html() method for PHP versions earlier than 5.3.6.
 * 3beaf52: [Security] Disabled the BCryptPasswordEncoder tests for PHP versions lower than 5.3.7.

* 2.3.0-BETA2 (2013-05-10)

 * 97bee20: Pass exceptions from the ExceptionListener to Monolog
 * be42dbc: [HttpFoundation][File][UploadedFile] Fix guessClientExtension() method
 * a5441b2: Fixed parsing of leading blank lines in folded scalars. Closes #7989.
 * e8d5d16: Fixed Loader import
 * bd0c48c: [Console] moved the IO configuration to its own method
 * fdb4b1f: [Console] moved --help support to allow proper behavior with other passed options
 * dd0e138: Eased translationNodeVisitor overriding in TranslationExtension
 * 853f681: fixed request scope issues (refs #7457)
 * 60edc58: Fixed fatal error in normalize/denormalizeObject.
 * 78e3710: ProxyManager Bridge
 * 41805c0: [Crawler] Add proper validation of node argument of method add
 * 7933971: [Form] Added radio button for empty value to expanded single-choice fields
 * 0586c7e: made some optimization when parsing YAML files
 * 1856df3: [Security] fixed wrong merge (refs #4776)
 * 5b7e1e6: added a missing check for the provider key
 * f1c2ab7: [DependencyInjection] Add a method map to avoid computing method names from service names
 * ea633f5: [HttpKernel] Avoid updating the context if the request did not change
 * 997d549: [HttpFoundation] Avoid a few unnecessary str_replace() calls
 * f5e7f24: [HttpFoundation] Optimize ServerBag::getHeaders()
 * 59b78c7: [Validator] Fixed: $traverse and $deep is passed to the visitor from Validator::validate()
 * bcb5400: [Form] Fixed transform()/reverseTransform() to always throw TransformationFailedExceptions
 * 7b2ebbf: [Form] Fixed: String validation groups are never interpreted as callbacks
 * 0610750: if the repository method returns an array ensure that it's internal poin...
 * dcced01: [Form] Improved multi-byte handling of NumberToLocalizedStringTransformer
 * 90a20d7: [Translation] Made translation domain defaults in Translator consistent with TranslatorInterface
 * 549a308: [Form] Fixed CSRF error messages to be translated and added "csrf_message" option
