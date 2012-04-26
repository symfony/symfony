CHANGELOG for 2.1.x
===================

This changelog references the relevant changes (bug and security fixes) made
in 2.1 minor versions.

To get the diff for a specific change, go to https://github.com/symfony/symfony/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/symfony/symfony/compare/v2.1.0...v2.1.1

2.1.0
-----

### AbstractDoctrineBundle

 * This bundle has been removed and the relevant code has been moved to the Doctrine bridge

### DoctrineBundle

 * This bundle has been moved to the Doctrine organization
 * added optional `group_by` property to `EntityType` that supports either a
   `PropertyPath` or a `\Closure` that is evaluated on the entity choices
 * The `em` option for the `UniqueEntity` constraint is now optional (and should
   probably not be used anymore).

### FrameworkBundle

 * changed the default extension for XLIFF files from .xliff to .xlf
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

### SwiftmailerBundle

 * This bundle has been moved to its own repository (https://github.com/symfony/SwiftmailerBundle)
 * moved the data collector to the bridge
 * replaced MessageLogger class with the one from Swiftmailer 4.1.3
