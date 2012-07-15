CHANGELOG
=========

2.1.0
-----

 * moved the translation files to the Form and Validator components
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
 * Removed 'auto_start' configuration parameter from session config. The session will
   start on demand.
 * [BC BREAK] TemplateNameParser::parseFromFilename() has been moved to a dedicated
   parser: TemplateFilenameParser::parse().
 * [BC BREAK] Kernel parameters are replaced by their value wherever they appear
   in Route patterns, requirements and defaults. Use '%%' as the escaped value for '%'.
 * [BC BREAK] Switched behavior of flash messages to expire flash messages on retrieval
   using Symfony\Component\HttpFoundation\Session\Flash\FlashBag as opposed to on
   next pageload regardless of whether they are displayed or not.