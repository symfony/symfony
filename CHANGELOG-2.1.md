CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

2.1.0
-----

### DoctrineBrige

 * added a default implementation of the ManagerRegistry
 * added a session storage for Doctrine DBAL

### AbstractDoctrineBundle

 * This bundle has been removed and the relevant code has been moved to the Doctrine bridge

### DoctrineBundle

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

### SecurityBundle

 * [BC BREAK] The Firewall listener is now registered after the Router one.
   It means that specific Firewall URLs (like /login_check and /logout must now have proper
   route defined in your routing configuration)

 * [BC BREAK] refactored the user provider configuration. The configuration changed for the chain provider and the memory provider:

   Before:

        security:
            providers:
                my_chain_provider:
                    providers: [my_memory_provider, my_doctrine_provider]
                my_memory_provider:
                    users:
                        toto: { password: foobar, roles: [ROLE_USER] }
                        foo: { password: bar, roles: [ROLE_USER, ROLE_ADMIN] }

   After:

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

 * added a validator for the user password

### SwiftmailerBundle

 * moved the data collector to the bridge
 * replaced MessageLogger class with the one from Swiftmailer 4.1.3

### TwigBundle

 * added the real template name when an error occurs in a Twig template

### WebProfilerBundle

 * added a routing panel
 * added a timeline panel
 * The toolbar position can now be configured via the `position` option (can be `top` or `bottom`)

### Config

 * implemented `Serializable` on resources

### Console

 * made the defaults (helper set, commands, input definition) in Application more easily customizable
 * added support for the shell even if readline is not available

### ClassLoader

 * added support for loading globally-installed PEAR packages

### DomCrawler

 * added a way to get parsing errors for Crawler::addHtmlContent() and Crawler::addXmlContent() via libxml functions
 * added support for submitting a form without a submit button

### Finder

 * Finder::exclude() now supports an array of directories as an argument

### Form

 * added support for validation groups as callbacks
 * made the translation catalogue configurable via the "translation_domain" option
 * added Form::getErrorsAsString() to help debugging forms
 * allowed setting different options for RepeatedType fields (like the label)

### HttpFoundation

 * made Response::prepare() method the place to enforce HTTP specification
 * [BC BREAK] moved management of the locale from the Session class to the Request class
 * added a generic access to the PHP built-in filter mechanism: ParameterBag::filter()
 * made FileBinaryMimeTypeGuesser command configurable
 * added Request::getUser() and Request::getPassword()
 * added support for the PATCH method in Request
 * removed the ContentTypeMimeTypeGuesser class as it is deprecated and never used on PHP 5.3
 * added ResponseHeaderBag::makeDisposition() (implements RFC 6266)
 * made mimetype to extension conversion configurable

### HttpKernel

 * added a Stopwatch class
 * added WarmableInterface
 * improved extensibility between bundles
 * added a File-based profiler storage
 * added a MongoDB-based profiler storage

### Locale

 * added Locale::getIcuVersion() and Locale::getIcuDataVersion()

### Routing

 * added a TraceableUrlMatcher
 * added the possibility to define default values and requirements for placeholders in prefix
 * added RouterInterface::getRouteCollection

### Security

 * after login, the user is now redirected to `default_target_path` if `use_referer` is true and the referrer is the `login_path`.
 * added a way to remove a token from a session

### Translation

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

### Yaml

 * Yaml::parse() does not evaluate loaded files as PHP files by default anymore (call Yaml::enablePhpParsing() to get back the old behavior)
