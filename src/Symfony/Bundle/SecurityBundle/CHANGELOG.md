CHANGELOG
=========

5.0.0
-----

 * The `switch_user.stateless` firewall option has been removed.
 * Removed the ability to configure encoders using `argon2i` or `bcrypt` as algorithm, use `auto` instead
 * The `simple_form` and `simple_preauth` authentication listeners have been removed,
   use Guard instead.
 * The `SimpleFormFactory` and `SimplePreAuthenticationFactory` classes have been removed,
   use Guard instead.
 * Removed `LogoutUrlHelper` and `SecurityHelper` templating helpers, use Twig instead
 * Removed the `logout_on_user_change` firewall option
 * Removed the `threads` encoder option
 * Removed the `security.authentication.trust_resolver.anonymous_class` parameter
 * Removed the `security.authentication.trust_resolver.rememberme_class` parameter
 * Removed the `security.user.provider.in_memory.user` service.

4.4.0
-----

 * Added new `argon2id` encoder, undeprecated the `bcrypt` and `argon2i` ones (using `auto` is still recommended by default.)
 * Deprecated the usage of "query_string" without a "search_dn" and a "search_password" config key in Ldap factories.
 * Marked the `SecurityDataCollector` class as `@final`.

4.3.0
-----

 * Added `anonymous: lazy` mode to firewalls to make them (not) start the session as late as possible
 * Added new encoder types: `auto` (recommended), `native` and `sodium`
 * The normalization of the cookie names configured in the `logout.delete_cookies`
   option is deprecated and will be disabled in Symfony 5.0. This affects to cookies
   with dashes in their names. For example, starting from Symfony 5.0, the `my-cookie`
   name will delete `my-cookie` (with a dash) instead of `my_cookie` (with an underscore).

4.2.0
-----

 * Using the `security.authentication.trust_resolver.anonymous_class` and
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use custom tokens extend the existing
   `Symfony\Component\Security\Core\Authentication\Token\AnonymousToken`.
   or `Symfony\Component\Security\Core\Authentication\Token\RememberMeToken`.
 * Added `Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass`
 * Added `json_login_ldap` authentication provider to use LDAP authentication with a REST API.
 * Made remember-me cookies inherit their default config from `framework.session.cookie_*`
   and added an "auto" mode to their "secure" config option to make them secure on HTTPS automatically.
 * Deprecated the `simple_form` and `simple_preauth` authentication listeners, use Guard instead.
 * Deprecated the `SimpleFormFactory` and `SimplePreAuthenticationFactory` classes, use Guard instead.
 * Added `port` in access_control
 * Added individual voter decisions to the profiler

4.1.0
-----

 * The `switch_user.stateless` firewall option is deprecated, use the `stateless` option instead.
 * The `logout_on_user_change` firewall option is deprecated.
 * deprecated `SecurityUserValueResolver`, use
   `Symfony\Component\Security\Http\Controller\UserValueResolver` instead.

4.0.0
-----

 * removed `FirewallContext::getContext()`
 * made `FirewallMap::$container` and `::$map` private
 * made the first `UserPasswordEncoderCommand::_construct()` argument mandatory
 * `UserPasswordEncoderCommand` does not extend `ContainerAwareCommand` anymore
 * removed support for voters that don't implement the `VoterInterface`
 * removed HTTP digest authentication
 * removed command `acl:set` along with `SetAclCommand` class
 * removed command `init:acl` along with `InitAclCommand` class
 * removed `acl` configuration key and related services, use symfony/acl-bundle instead
 * removed auto picking the first registered provider when no configured provider on a firewall and ambiguous
 * the firewall option `logout_on_user_change` is now always true, which will trigger a logout if the user changes
   between requests
 * the `switch_user.stateless` firewall option is `true` for stateless firewalls

3.4.0
-----

 * Added new `security.helper` service that is an instance of `Symfony\Component\Security\Core\Security`
   and provides shortcuts for common security tasks.
 * Tagging voters with the `security.voter` tag without implementing the
   `VoterInterface` on the class is now deprecated and will be removed in 4.0.
 * [BC BREAK] `FirewallContext::getListeners()` now returns `\Traversable|array`
 * added info about called security listeners in profiler
 * Added `logout_on_user_change` to the firewall options. This config item will
   trigger a logout when the user has changed. Should be set to true to avoid
   deprecations in the configuration.
 * deprecated HTTP digest authentication
 * deprecated command `acl:set` along with `SetAclCommand` class
 * deprecated command `init:acl` along with `InitAclCommand` class
 * Added support for the new Argon2i password encoder
 * added `stateless` option to the `switch_user` listener
 * deprecated auto picking the first registered provider when no configured provider on a firewall and ambiguous

3.3.0
-----

 * Deprecated instantiating `UserPasswordEncoderCommand` without its constructor
   arguments fully provided.
 * Deprecated `UserPasswordEncoderCommand::getContainer()` and relying on the
  `ContainerAwareCommand` sub class or `ContainerAwareInterface` implementation for this command.
 * Deprecated the `FirewallMap::$map` and `$container` properties.
 * [BC BREAK] Keys of the `users` node for `in_memory` user provider are no longer normalized.
 * deprecated `FirewallContext::getListeners()`

3.2.0
-----

 * Added the `SecurityUserValueResolver` to inject the security users in actions via
   `Symfony\Component\Security\Core\User\UserInterface` in the method signature.

3.0.0
-----

 * Removed the `security.context` service.

2.8.0
-----

 * deprecated the `key` setting of `anonymous`, `remember_me` and `http_digest`
   in favor of the `secret` setting.
 * deprecated the `intention` firewall listener setting in favor of the `csrf_token_id`.

2.6.0
-----

 * Added the possibility to override the default success/failure handler
   to get the provider key and the options injected
 * Deprecated the `security.context` service for the `security.token_storage` and
   `security.authorization_checker` services.

2.4.0
-----

 * Added 'host' option to firewall configuration
 * Added 'csrf_token_generator' and 'csrf_token_id' options to firewall logout
   listener configuration to supersede/alias 'csrf_provider' and 'intention'
   respectively
 * Moved 'security.secure_random' service configuration to FrameworkBundle

2.3.0
-----

 * allowed for multiple IP address in security access_control rules

2.2.0
-----

 * Added PBKDF2 Password encoder
 * Added BCrypt password encoder

2.1.0
-----

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
                    csrf_parameter: _csrf_token                   # Optional (defaults to "_csrf_token")
                    csrf_provider:  security.csrf.token_generator # Required to enable protection
                    intention:      logout                        # Optional (defaults to "logout")
    ```

    If the LogoutListener has CSRF protection enabled but cannot validate a token,
   then a LogoutException will be thrown.

 * Added `logout_url` templating helper and Twig extension, which may be used to
   generate logout URL's within templates. The security firewall's config key
   must be specified. If a firewall's logout listener has CSRF protection
   enabled, a token will be automatically added to the generated URL.
