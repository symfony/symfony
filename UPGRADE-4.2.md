UPGRADE FROM 4.1 to 4.2
=======================

Cache
-----

 * Deprecated `CacheItem::getPreviousTags()`, use `CacheItem::getMetadata()` instead.

Security
--------

 * Using the `has_role()` function in security expressions is deprecated, use the `is_granted()` function instead.
 * Not returning an array of 3 elements from `FirewallMapInterface::getListeners()` is deprecated, the 3rd element
   must be an instance of `LogoutListener` or `null`.
 * Passing custom class names to the
   `Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver` to define
   custom anonymous and remember me token classes is deprecated. To
   use custom tokens, extend the existing `Symfony\Component\Security\Core\Authentication\Token\AnonymousToken`
   or `Symfony\Component\Security\Core\Authentication\Token\RememberMeToken`.

SecurityBundle
--------------

 * Passing a `FirewallConfig` instance as 3rd argument to the `FirewallContext` constructor is deprecated,
   pass a `LogoutListener` instance instead.
 * Using the `security.authentication.trust_resolver.anonymous_class` and
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use
   custom tokens extend the existing AnonymousToken and RememberMeToken.
