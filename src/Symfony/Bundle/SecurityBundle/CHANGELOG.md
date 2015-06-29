CHANGELOG
=========

2.8.0
-----

 * deprecated the `key` setting of `anonymous` and `remember_me` in favor of the
   `secret` setting.

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
   listener configuration to supercede/alias 'csrf_provider' and 'intention'
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
