CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) made
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

2.1.0
-----

### DoctrineBridge

 * added a default implementation of the ManagerRegistry
 * added a session storage for Doctrine DBAL
 * DoctrineOrmTypeGuesser now guesses "collection" for array Doctrine type

### TwigBridge

 * added a csrf_token function
 * added a way to specify a default domain for a Twig template (via the 'trans_default_domain' tag)

### AbstractDoctrineBundle

 * This bundle has been removed and the relevant code has been moved to the Doctrine bridge

### DoctrineBundle

 * This bundle has been moved to the Doctrine organization
 * added optional `group_by` property to `EntityType` that supports either a
   `PropertyPath` or a `\Closure` that is evaluated on the entity choices
 * The `em` option for the `UniqueEntity` constraint is now optional (and should
   probably not be used anymore).

### FrameworkBundle

 * moved Symfony\Bundle\FrameworkBundle\ContainerAwareEventDispatcher to Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
 * moved Symfony\Bundle\FrameworkBundle\Debug\TraceableEventDispatcher to Symfony\Component\EventDispatcher\ContainerAwareTraceableEventDispatcher
 * added a router:match command
 * added a config:dump-reference command
 * added a server:run command
 * added kernel.event_subscriber tag
 * added a way to create relative symlinks when running assets:install command (--relative option)
 * added Controller::getUser()
 * [BC BREAK] assets_base_urls and base_urls merging strategy has changed
 * changed the default profiler storage to use the filesystem instead of SQLite
 * added support for placeholders in route defaults and requirements (replaced
   by the value set in the service container)
 * added Filesystem component as a dependency
 * added support for hinclude (use ``standalone: 'js'`` in render tag)
 * session options: lifetime, path, domain, secure, httponly were deprecated.
   Prefixed versions should now be used instead: cookie_lifetime, cookie_path,
   cookie_domain, cookie_secure, cookie_httponly
 * [BC BREAK] following session options: 'lifetime', 'path', 'domain', 'secure',
   'httponly' are now prefixed with cookie_ when dumped to the container
 * Added `handler_id` configuration under `session` key to represent `session.handler`
   service, defaults to `session.handler.native_file`.
 * Added `gc_maxlifetime`, `gc_probability`, and `gc_divisor` to session
   configuration. This means session garbage collection has a
  `gc_probability`/`gc_divisor` chance of being run. The `gc_maxlifetime` defines
   how long a session can idle for. It is different from cookie lifetime which
   declares how long a cookie can be stored on the remote client.


### MonologBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/MonologBundle)

### SecurityBundle

 * [BC BREAK] The custom factories for the firewall configuration are now
   registered during the build method of bundles instead of being registered
   by the end-user (you need to remove the 'factories' keys in your security
   configuration).

 * [BC BREAK] The Firewall listener is now registered after the Router one. This
   means that specific Firewall URLs (like /login_check and /logout must now
   have proper route defined in your routing configuration)

 * [BC BREAK] refactored the user provider configuration. The configuration
   changed for the chain provider and the memory provider:

    Before:

    ``` yaml
    security:
        providers:
            my_chain_provider:
                providers: [my_memory_provider, my_doctrine_provider]
            my_memory_provider:
                users:
                    toto: { password: foobar, roles: [ROLE_USER] }
                    foo: { password: bar, roles: [ROLE_USER, ROLE_ADMIN] }
    ```

    After:

    ``` yaml
    security:
        providers:
            my_chain_provider:
                chain:
                    providers: [my_memory_provider, my_doctrine_provider]
            my_memory_provider:
                memory:
                    users:
                        toto: { password: foobar, roles: [ROLE_USER] }
                        foo: { password: bar, roles: [ROLE_USER, ROLE_ADMIN] }
    ```

 * [BC BREAK] Method `equals` was removed from `UserInterface` to its own new
   `EquatableInterface`. The user class can now implement this interface to override
   the default implementation of users equality test.

 * added a validator for the user password
 * added 'erase_credentials' as a configuration key (true by default)
 * added new events: `security.authentication.success` and `security.authentication.failure`
   fired on authentication success/failure, regardless of authentication method,
   events are defined in new event class: `Symfony\Component\Security\Core\AuthenticationEvents`.

 * Added optional CSRF protection to LogoutListener:

    ``` yaml
    security:
        firewalls:
            default:
                logout:
                    path: /logout_path
                    target: /
                    csrf_parameter: _csrf_token        # Optional (defaults to "_csrf_token")
                    csrf_provider:  form.csrf_provider # Required to enable protection
                    intention:      logout             # Optional (defaults to "logout")
    ```

    If the LogoutListener has CSRF protection enabled but cannot validate a token,
   then a LogoutException will be thrown.

 * Added `logout_url` templating helper and Twig extension, which may be used to
   generate logout URL's within templates. The security firewall's config key
   must be specified. If a firewall's logout listener has CSRF protection
   enabled, a token will be automatically added to the generated URL.

### SwiftmailerBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/SwiftmailerBundle)
 * moved the data collector to the bridge
 * replaced MessageLogger class with the one from Swiftmailer 4.1.3

### TwigBundle

 * added the real template name when an error occurs in a Twig template
 * added the twig:lint command that will validate a Twig template syntax.

### WebProfilerBundle

 * [BC BREAK] You must clear old profiles after upgrading to 2.1 (don't forget to
   remove the table if you are using a DB)
 * added support for the request method
 * added a routing panel
 * added a timeline panel
 * The toolbar position can now be configured via the `position` option (can be `top` or `bottom`)

### BrowserKit

 * [BC BREAK] The CookieJar internals have changed to allow cookies with the same name on different sub-domains/sub-paths

### Config

 * added a way to add documentation on configuration
 * implemented `Serializable` on resources
 * LoaderResolverInterface is now used instead of LoaderResolver for type hinting

### Console

 * added a --raw option to the list command
 * added support for STDERR in the console output class (errors are now sent to STDERR)
 * made the defaults (helper set, commands, input definition) in Application more easily customizable
 * added support for the shell even if readline is not available
 * added support for process isolation in Symfony shell via `--process-isolation` switch
 * added support for `--`, which disables options parsing after that point (tokens will be parsed as arguments)

### ClassLoader

 * added a DebugClassLoader able to wrap any autoloader providing a findFile method
 * added a new ApcClassLoader and XcacheClassLoader using composition to wrap other loaders
 * added a new ClassLoader which does not distinguish between namespaced and pear-like classes (as the PEAR
   convention is a subset of PSR-0) and supports using Composer's namespace maps
 * added a class map generator
 * added support for loading globally-installed PEAR packages

### DependencyInjection

 * component exceptions that inherit base SPL classes are now used exclusively (this includes dumped containers)

### DomCrawler

 * refactored the Form class internals to support multi-dimensional fields (the public API is backward compatible)
 * added a way to get parsing errors for Crawler::addHtmlContent() and Crawler::addXmlContent() via libxml functions
 * added support for submitting a form without a submit button

### EventDispatcher

 * added a reference to the EventDispatcher on the Event
 * added a reference to the Event name on the event
 * added fluid interface to the dispatch() method which now returns the Event object
 * added GenericEvent event class

### Filesystem

 * created this new component

### Finder

 * Finder::exclude() now supports an array of directories as an argument
 * new methods Finder::contains() and Finder::notContains() support searching based on content
 * Comparator now supports != operator

### Form

 * [BC BREAK] ``read_only`` field attribute now renders as ``readonly="readonly"``, use ``disabled`` instead
 * [BC BREAK] child forms now aren't validated anymore by default
 * made validation of form children configurable (new option: cascade_validation)
 * added support for validation groups as callbacks
 * made the translation catalogue configurable via the "translation_domain" option
 * added Form::getErrorsAsString() to help debugging forms
 * allowed setting different options for RepeatedType fields (like the label)
 * added support for empty form name at root level, this enables rendering forms
   without form name prefix in field names
 * [BC BREAK] form and field names must start with a letter, digit or underscore
   and only contain letters, digits, underscores, hyphens and colons
 * [BC BREAK] changed default name of the prototype in the "collection" type
   from "$$name$$" to "\__name\__". No dollars are appended/prepended to custom
   names anymore.
 * [BC BREAK] improved ChoiceListInterface
 * [BC BREAK] added SimpleChoiceList and LazyChoiceList as replacement of
   ArrayChoiceList
 * added ChoiceList and ObjectChoiceList to use objects as choices
 * [BC BREAK] removed EntitiesToArrayTransformer and EntityToIdTransformer.
   The former has been replaced by CollectionToArrayTransformer in combination
   with EntityChoiceList, the latter is not required in the core anymore.

 * [BC BREAK] renamed

   * ArrayToBooleanChoicesTransformer to ChoicesToBooleanArrayTransformer
   * ScalarToBooleanChoicesTransformer to ChoiceToBooleanArrayTransformer
   * ArrayToChoicesTransformer to ChoicesToValuesTransformer
   * ScalarToChoiceTransformer to ChoiceToValueTransformer

    to be consistent with the naming in ChoiceListInterface.

 * [BC BREAK] removed FormUtil::toArrayKey() and FormUtil::toArrayKeys().
   They were merged into ChoiceList and have no public equivalent anymore.
 * choice fields now throw a FormException if neither the "choices" nor the
   "choice_list" option is set
 * the radio type is now a child of the checkbox type
 * the collection, choice (with multiple selection) and entity (with multiple
   selection) types now make use of addXxx() and removeXxx() methods in your
   model if you set "by_reference" to false. For a custom, non-recognized
   singular form, set the "property_path" option like this: "plural|singular"
 * forms now don't create an empty object anymore if they are completely
   empty and not required. The empty value for such forms is null.
 * added constant Guess::VERY_HIGH_CONFIDENCE
 * [BC BREAK] FormType::getParent() does not see default options anymore
 * [BC BREAK] The methods `add`, `remove`, `setParent`, `bind` and `setData`
   in class Form now throw an exception if the form is already bound
 * fields of constrained classes without a NotBlank or NotNull constraint are
   set to not required now, as stated in the docs
 * fixed TimeType and DateTimeType to not display seconds when "widget" is
   "single_text" unless "with_seconds" is set to true
 * checkboxes of in an expanded multiple-choice field don't include the choice
   in their name anymore. Their names terminate with "[]" now.
 * [BC BREAK] FormType::getDefaultOptions() and FormType::getAllowedOptionValues()
   don't receive an options array anymore.
 * deprecated FormValidatorInterface and substituted its implementations
   by event subscribers
 * simplified CSRF protection and removed the csrf type
 * deprecated FieldType and merged it into FormType
 * [BC BREAK] renamed "field_*" theme blocks to "form_*" and "field_widget" to
   "input"
 * ValidatorTypeGuesser now guesses "collection" for array type constraint
 * added method `guessPattern` to FormTypeGuesserInterface to guess which pattern to use in the HTML5 attribute "pattern"

### HttpFoundation

 * added a getTargetUrl method to RedirectResponse
 * added support for streamed responses
 * made Response::prepare() method the place to enforce HTTP specification
 * [BC BREAK] moved management of the locale from the Session class to the Request class
 * added a generic access to the PHP built-in filter mechanism: ParameterBag::filter()
 * made FileBinaryMimeTypeGuesser command configurable
 * added Request::getUser() and Request::getPassword()
 * added support for the PATCH method in Request
 * removed the ContentTypeMimeTypeGuesser class as it is deprecated and never used on PHP 5.3
 * added ResponseHeaderBag::makeDisposition() (implements RFC 6266)
 * made mimetype to extension conversion configurable
 * [BC BREAK] Moved all session related classes and interfaces into own namespace, as
   `Symfony\Component\HttpFoundation\Session` and renamed classes accordingly.
   Session handlers are located in the subnamespace `Symfony\Component\HttpFoundation\Session\Handler`.
 * SessionHandlers must implement `\SessionHandlerInterface` or extend from the
   `Symfony\Component\HttpFoundation\Storage\Handler\NativeSessionHandler` base class.
 * Added internal storage driver proxy mechanism for forward compatibility with
   PHP 5.4 `\SessionHandler` class.
 * Added session handlers for PHP native MongoDb, Memcache, Memcached and SQLite session
   save handlers.
 * Added session handlers for custom Memcache, Memcached and Null session save handlers.
 * [BC BREAK] Removed `NativeSessionStorage` and replaced with `NativeFileSessionHandler`.
 * [BC BREAK] `SessionStorageInterface` methods removed: `write()`, `read()` and
   `remove()`.  Added `getBag()`, `registerBag()`.  The `NativeSessionStorage` class
   is a mediator for the session storage internals including the session handlers
   which do the real work of participating in the internal PHP session workflow.
 * [BC BREAK] Introduced mock implementations of `SessionStorage` to enable unit
   and functional testing without starting real PHP sessions.  Removed
   `ArraySessionStorage`, and replaced with `MockArraySessionStorage` for unit
   tests; removed `FilesystemSessionStorage`, and replaced with`MockFileSessionStorage`
   for functional tests.  These do not interact with global session ini
   configuration values, session functions or `$_SESSION` superglobal. This means
   they can be configured directly allowing multiple instances to work without
   conflicting in the same PHP process.
 * [BC BREAK] Removed the `close()` method from the `Session` class, as this is
   now redundant.
 * Deprecated the following methods from the Session class: `setFlash()`, `setFlashes()`
   `getFlash()`, `hasFlash()`, and `removeFlash()`. Use `getFlashBag()` instead
   which returns a `FlashBagInterface`.
 * `Session->clear()` now only clears session attributes as before it cleared
   flash messages and attributes. `Session->getFlashBag()->all()` clears flashes now.
 * Session data is now managed by `SessionBagInterface` to better encapsulate
   session data.
 * Refactored session attribute and flash messages system to their own
  `SessionBagInterface` implementations.
 * Added `FlashBag`. Flashes expire when retrieved by `get()` or `all()`. This
   implementation is ESI compatible.
 * Added `AutoExpireFlashBag` (default) to replicate Symfony 2.0.x auto expire
   behaviour of messages auto expiring.
   after one page page load.  Messages must be retrieved by `get()` or `all()`.
 * Added `Symfony\Component\HttpFoundation\Attribute\AttributeBag` to replicate
   attributes storage behaviour from 2.0.x (default).
 * Added `Symfony\Component\HttpFoundation\Attribute\NamespacedAttributeBag` for
   namespace session attributes.
 * Flash API can stores messages in an array so there may be multiple messages
   per flash type.  The old `Session` class API remains without BC break as it
   will allow single messages as before.
 * Added basic session meta-data to the session to record session create time,
   last updated time, and the lifetime of the session cookie that was provided
   to the client.
 * Request::getClientIp() method doesn't take a parameter anymore but bases
   itself on the trustProxy parameter.
 * Added isMethod() to Request object.
 * [BC BREAK] The methods `getPathInfo()`, `getBaseUrl()` and `getBasePath()` of
   a `Request` now all return a raw value (vs a urldecoded value before). Any call
   to one of these methods must be checked and wrapped in a `rawurldecode()` if
   needed.

### HttpKernel

 * added CacheClearerInterface
 * added a kernel.terminate event
 * added a Stopwatch class
 * added WarmableInterface
 * improved extensibility between bundles
 * added Memcache(d)-based profiler storages
 * added a File-based profiler storage
 * added a MongoDB-based profiler storage
 * added a Redis-based profiler storage
 * moved Filesystem class to its own component

### Locale

 * added Locale::getIcuVersion() and Locale::getIcuDataVersion()

### Process

 * added ProcessBuilder

### Routing

 * the UrlMatcher does not throw a \LogicException any more when the required scheme is not the current one
 * added a TraceableUrlMatcher
 * added the possibility to define options, default values and requirements for placeholders in prefix, including imported routes
 * added RouterInterface::getRouteCollection
 * [BC BREAK] the UrlMatcher urldecodes the route parameters only once, they were decoded twice before.
   Note that the `urldecode()` calls have been changed for a single `rawurldecode()` in order to support `+` for input paths.
 * added RouteCollection::getRoot method to retrieve the root of a RouteCollection tree
 * [BC BREAK] made RouteCollection::setParent private which could not have been used anyway without creating inconsistencies
 * [BC BREAK] RouteCollection::remove also removes a route from parent collections (not only from its children)

### Security

 * after login, the user is now redirected to `default_target_path` if `use_referer` is true and the referrer is the `login_path`.
 * added a way to remove a token from a session
 * [BC BREAK] changed `MutableAclInterface::setParentAcl` to accept `null`, review your implementation to reflect this change.
 * `ObjectIdentity::fromDomainObject`, `UserSecurityIdentity::fromAccount` and `UserSecurityIdentity::fromToken` now return
   correct identities for proxies objects (e.g. Doctrine proxies)

### Serializer

 * [BC BREAK] changed `GetSetMethodNormalizer`'s key names from all lowercased to camelCased (e.g. `mypropertyvalue` to `myPropertyValue`)
 * [BC BREAK] convert the `item` XML tag to an array

    ``` xml
    <?xml version="1.0"?>
    <response>
        <item><title><![CDATA[title1]]></title></item><item><title><![CDATA[title2]]></title></item>
    </response>
    ```

    Before:

        Array()

    After:

        Array(
            [item] => Array(
                [0] => Array(
                    [title] => title1
                )
                [1] => Array(
                    [title] => title2
                )
            )
        )

### Translation

 * changed the default extension for XLIFF files from .xliff to .xlf
 * added support for gettext
 * added support for more than one fallback locale
 * added support for translations in ResourceBundles
 * added support for extracting translation messages from templates (Twig and PHP)
 * added dumpers for translation catalogs
 * added support for QT translations

### Validator

 * added support for `ctype_*` assertions in `TypeValidator`
 * added a Size validator
 * added a SizeLength validator
 * improved the ImageValidator with min width, max width, min height, and max height constraints
 * added support for MIME with wildcard in FileValidator
 * changed Collection validator to add "missing" and "extra" errors to
   individual fields
 * changed default value for `extraFieldsMessage` and `missingFieldsMessage`
   in Collection constraint
 * made ExecutionContext immutable
 * deprecated Constraint methods `setMessage`, `getMessageTemplate` and
   `getMessageParameters`
 * added support for dynamic group sequences with the GroupSequenceProvider pattern
 * [BC BREAK] ConstraintValidatorInterface method `isValid` has been renamed to
   `validate`, its return value was dropped. ConstraintValidator still contains
   `isValid` for BC

### Yaml

 * Yaml::parse() does not evaluate loaded files as PHP files by default anymore (call Yaml::enablePhpParsing() to get back the old behavior)
