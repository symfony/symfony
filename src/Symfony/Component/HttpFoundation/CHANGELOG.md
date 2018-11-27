CHANGELOG
=========

4.2.0
-----

 * the default value of the "$secure" and "$samesite" arguments of Cookie's constructor
   will respectively change from "false" to "null" and from "null" to "lax" in Symfony
   5.0, you should define their values explicitly or use "Cookie::create()" instead.
 * added `matchPort()` in RequestMatcher

4.1.3
-----

 * [BC BREAK] Support for the IIS-only `X_ORIGINAL_URL` and `X_REWRITE_URL`
   HTTP headers has been dropped for security reasons.

4.1.0
-----

 * Query string normalization uses `parse_str()` instead of custom parsing logic.
 * Passing the file size to the constructor of the `UploadedFile` class is deprecated.
 * The `getClientSize()` method of the `UploadedFile` class is deprecated. Use `getSize()` instead.
 * added `RedisSessionHandler` to use Redis as a session storage
 * The `get()` method of the `AcceptHeader` class now takes into account the
   `*` and `*/*` default values (if they are present in the Accept HTTP header)
   when looking for items.
 * deprecated `Request::getSession()` when no session has been set. Use `Request::hasSession()` instead.
 * added `CannotWriteFileException`, `ExtensionFileException`, `FormSizeFileException`,
   `IniSizeFileException`, `NoFileException`, `NoTmpDirFileException`, `PartialFileException` to
   handle failed `UploadedFile`.
 * added `MigratingSessionHandler` for migrating between two session handlers without losing sessions
 * added `HeaderUtils`.

4.0.0
-----

 * the `Request::setTrustedHeaderName()` and `Request::getTrustedHeaderName()`
   methods have been removed
 * the `Request::HEADER_CLIENT_IP` constant has been removed, use
   `Request::HEADER_X_FORWARDED_FOR` instead
 * the `Request::HEADER_CLIENT_HOST` constant has been removed, use
   `Request::HEADER_X_FORWARDED_HOST` instead
 * the `Request::HEADER_CLIENT_PROTO` constant has been removed, use
   `Request::HEADER_X_FORWARDED_PROTO` instead
 * the `Request::HEADER_CLIENT_PORT` constant has been removed, use
   `Request::HEADER_X_FORWARDED_PORT` instead
 * checking for cacheable HTTP methods using the `Request::isMethodSafe()`
   method (by not passing `false` as its argument) is not supported anymore and
   throws a `\BadMethodCallException`
 * the `WriteCheckSessionHandler`, `NativeSessionHandler` and `NativeProxy` classes have been removed
 * setting session save handlers that do not implement `\SessionHandlerInterface` in
   `NativeSessionStorage::setSaveHandler()` is not supported anymore and throws a
   `\TypeError`

3.4.0
-----

 * implemented PHP 7.0's `SessionUpdateTimestampHandlerInterface` with a new
   `AbstractSessionHandler` base class and a new `StrictSessionHandler` wrapper
 * deprecated the `WriteCheckSessionHandler`, `NativeSessionHandler` and `NativeProxy` classes
 * deprecated setting session save handlers that do not implement `\SessionHandlerInterface` in `NativeSessionStorage::setSaveHandler()`
 * deprecated using `MongoDbSessionHandler` with the legacy mongo extension; use it with the mongodb/mongodb package and ext-mongodb instead
 * deprecated `MemcacheSessionHandler`; use `MemcachedSessionHandler` instead

3.3.0
-----

 * the `Request::setTrustedProxies()` method takes a new `$trustedHeaderSet` argument,
   see http://symfony.com/doc/current/components/http_foundation/trusting_proxies.html for more info,
 * deprecated the `Request::setTrustedHeaderName()` and `Request::getTrustedHeaderName()` methods,
 * added `File\Stream`, to be passed to `BinaryFileResponse` when the size of the served file is unknown,
   disabling `Range` and `Content-Length` handling, switching to chunked encoding instead
 * added the `Cookie::fromString()` method that allows to create a cookie from a
   raw header string

3.1.0
-----

 * Added support for creating `JsonResponse` with a string of JSON data

3.0.0
-----

 * The precedence of parameters returned from `Request::get()` changed from "GET, PATH, BODY" to "PATH, GET, BODY"

2.8.0
-----

 * Finding deep items in `ParameterBag::get()` is deprecated since version 2.8 and
   will be removed in 3.0.

2.6.0
-----

 * PdoSessionHandler changes
   - implemented different session locking strategies to prevent loss of data by concurrent access to the same session
   - [BC BREAK] save session data in a binary column without base64_encode
   - [BC BREAK] added lifetime column to the session table which allows to have different lifetimes for each session
   - implemented lazy connections that are only opened when a session is used by either passing a dsn string
     explicitly or falling back to session.save_path ini setting
   - added a createTable method that initializes a correctly defined table depending on the database vendor

2.5.0
-----

 * added `JsonResponse::setEncodingOptions()` & `JsonResponse::getEncodingOptions()` for easier manipulation
   of the options used while encoding data to JSON format.

2.4.0
-----

 * added RequestStack
 * added Request::getEncodings()
 * added accessors methods to session handlers

2.3.0
-----

 * added support for ranges of IPs in trusted proxies
 * `UploadedFile::isValid` now returns false if the file was not uploaded via HTTP (in a non-test mode)
 * Improved error-handling of `\Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler`
   to ensure the supplied PDO handler throws Exceptions on error (as the class expects). Added related test cases
   to verify that Exceptions are properly thrown when the PDO queries fail.

2.2.0
-----

 * fixed the Request::create() precedence (URI information always take precedence now)
 * added Request::getTrustedProxies()
 * deprecated Request::isProxyTrusted()
 * [BC BREAK] JsonResponse does not turn a top level empty array to an object anymore, use an ArrayObject to enforce objects
 * added a IpUtils class to check if an IP belongs to a CIDR
 * added Request::getRealMethod() to get the "real" HTTP method (getMethod() returns the "intended" HTTP method)
 * disabled _method request parameter support by default (call Request::enableHttpMethodParameterOverride() to
   enable it, and Request::getHttpMethodParameterOverride() to check if it is supported)
 * Request::splitHttpAcceptHeader() method is deprecated and will be removed in 2.3
 * Deprecated Flashbag::count() and \Countable interface, will be removed in 2.3

2.1.0
-----

 * added Request::getSchemeAndHttpHost() and Request::getUserInfo()
 * added a fluent interface to the Response class
 * added Request::isProxyTrusted()
 * added JsonResponse
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
   behaviour of messages auto expiring after one page page load.  Messages must
   be retrieved by `get()` or `all()`.
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
