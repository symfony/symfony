CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

2.1.0
-----

### DoctrineBridge

 * added a default implementation of the ManagerRegistry
 * added a session storage for Doctrine DBAL

### TwigBridge

 * added a csrf_token function
 * added a way to specify a default domain for a Twig template (via the 'trans_default_domain' tag)

### AbstractDoctrineBundle

 * This bundle has been removed and the relevant code has been moved to the Doctrine bridge

### DoctrineBundle

 * This bundle has been moved to the Doctrine organization
 * added optional `group_by` property to `EntityType` that supports either a `PropertyPath` or a `\Closure` that is evaluated on the entity choices
 * The `em` option for the `UniqueEntity` constraint is now optional (and should probably not be used anymore).

### FrameworkBundle

 * added a router:match command
 * added kernel.event_subscriber tag
 * added a way to create relative symlinks when running assets:install command (--relative option)
 * added Controller::getUser()
 * [BC BREAK] assets_base_urls and base_urls merging strategy has changed
 * changed the default profiler storage to use the filesystem instead of SQLite
 * added support for placeholders in route defaults and requirements (replaced by the value set in the service container)
 * added Filesystem component as a dependency
 * [BC BREAK] changed `session.xml` service name `session.storage.native` to `session.storage.native_file`
 * added new session storage drivers to session.xml: `session.storage.native_memcache`, `session.storage.native_memcached`,
   `session.storage.native_sqlite`, `session.storage.null`, `session.storage.memcache`,
   and `session.storage.memcached`.  Added `session.storage.mock_file` service for functional session testing.
 * removed `session.storage.filesystem` service.

### MonologBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/MonologBundle)

### SecurityBundle

 * [BC BREAK] The custom factories for the firewall configuration are now
   registered during the build method of bundles instead of being registered
   by the end-user (you need to remove the 'factories' keys in your security
   configuration).

 * [BC BREAK] The Firewall listener is now registered after the Router one. It
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
   `EquatableInterface`, now user class can implement this interface to override
   the default implementation of users equality test.

 * added a validator for the user password
 * added 'erase_credentials' as a configuration key (true by default)
 * added new events: `security.authentication.success` and `security.authentication.failure`
   fired on authentication success/failure, regardless of authentication method,
   events are defined in new event class: `Symfony\Component\Security\Core\AuthenticationEvents`.

### SwiftmailerBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/SwiftmailerBundle)
 * moved the data collector to the bridge
 * replaced MessageLogger class with the one from Swiftmailer 4.1.3

### TwigBundle

 * added the real template name when an error occurs in a Twig template

### WebProfilerBundle

[BC BREAK] You must clear old profiles after upgrading to 2.1 (don't forget to
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

### ClassLoader

 * added a class map generator
 * added support for loading globally-installed PEAR packages

### DependencyInjection

 * component exceptions that inherit base SPL classes are now used exclusively (this includes dumped containers)

### DomCrawler

 * refactor the Form class internals to support multi-dimensional fields (the public API is backward compatible)
 * added a way to get parsing errors for Crawler::addHtmlContent() and Crawler::addXmlContent() via libxml functions
 * added support for submitting a form without a submit button

### EventDispatcher

 * added a reference to the EventDispatcher on the Event
 * added a reference to the Event name on the event

### Filesystem

 * created this new component

### Finder

 * Finder::exclude() now supports an array of directories as an argument

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
   from "$$name$$" to "__name__". No dollars are appended/prepended to custom
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
   model
 * added options "add_method" and "remove_method" to collection and choice type
 * forms now don't create an empty object anymore if they are completely
   empty and not required. The empty value for such forms is null.
 * added constant Guess::VERY_HIGH_CONFIDENCE
 * FormType::getDefaultOptions() now sees default options defined by parent types
 * [BC BREAK] FormType::getParent() does not see default options anymore

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
 * Flashes are now stored as a bucket of messages per `$type` so there can be multiple messages per type.
   There are four interface constants for type, `FlashBagInterface::INFO`, `FlashBagInterface::NOTICE`,
   `FlashBagInterface::WARNING` and `FlashBagInterface::ERROR`.
 * Added `FlashBag` (default). Flashes expire when retrieved by `popFlashes()`.
   This makes the implementation ESI compatible.
 * Added `AutoExpireFlashBag` to replicate Symfony 2.0.x auto expire behaviour of messages auto expiring
   after one page page load.  Messages must be retrived by `pop()` or `popAll()`.
 * [BC BREAK] Removed the following methods from the Session class: `close()`, `setFlash()`, `setFlashes()`
   `getFlash()`, `hasFlash()`, andd `removeFlash()`.  `getFlashes() returns a `FlashBagInterface`.
 * `Session->clear()` now only clears session attributes as before it cleared flash messages and
   attributes. `Session->getFlashes()->popAll()` clears flashes now.
 * Added `AbstractSessionStorage` base class for session storage drivers.
 * Added `SessionSaveHandler` interface which storage drivers should implement after inheriting from
   `AbstractSessionStorage` when writing custom session save handlers.
 * [BC BREAK] `SessionStorageInterface` methods removed: `write()`, `read()` and `remove()`.  Added
   `getAttributes()`, `getFlashes()`.
 * Moved attribute storage to `AttributeBagInterface`.
 * Added `AttributeBag` to replicate attributes storage behaviour from 2.0.x (default).
 * Added `NamespacedAttributeBag` for namespace session attributes.
 * Session now implements `SessionInterface` making implementation customizable and portable.
 * [BC BREAK] Removed `NativeSessionStorage` and replaced with `NativeFileSessionStorage`.
 * Added session storage drivers for PHP native Memcache, Memcached and SQLite session save handlers.
 * Added session storage drivers for custom Memcache, Memcached and Null session save handlers.
 * Removed `FilesystemSessionStorage`, use `MockFileSessionStorage` for functional testing instead.

### HttpKernel

 * added CacheClearerInterface
 * added a kernel.terminate event
 * added a Stopwatch class
 * added WarmableInterface
 * improved extensibility between bundles
 * added a File-based profiler storage
 * added a MongoDB-based profiler storage
 * moved Filesystem class to its own component

### Locale

 * added Locale::getIcuVersion() and Locale::getIcuDataVersion()

### Process

 * added ProcessBuilder

### Routing

 * added a TraceableUrlMatcher
 * added the possibility to define default values and requirements for placeholders in prefix, including imported routes
 * added RouterInterface::getRouteCollection

### Security

 * after login, the user is now redirected to `default_target_path` if `use_referer` is true and the referrer is the `login_path`.
 * added a way to remove a token from a session
 * [BC BREAK] changed `MutableAclInterface::setParentAcl` to accept `null`, review your implementation to reflect this change.

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

### Yaml

 * Yaml::parse() does not evaluate loaded files as PHP files by default anymore (call Yaml::enablePhpParsing() to get back the old behavior)
