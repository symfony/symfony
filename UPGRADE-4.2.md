UPGRADE FROM 4.1 to 4.2
=======================

Security
--------

 * Using the `has_role()` function in security expressions is deprecated, use the `is_granted()` function instead.
 * Passing custom class names to the
   `Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver` to define
   custom anonymous and remember me token classes is deprecated. To
   use custom tokens, extend the existing `Symfony\Component\Security\Core\Authentication\Token\AnonymousToken`
   or `Symfony\Component\Security\Core\Authentication\Token\RememberMeToken`.

SecurityBundle
--------------

 * Using the `security.authentication.trust_resolver.anonymous_class` and 
   `security.authentication.trust_resolver.rememberme_class` parameters to define
   the token classes is deprecated. To use
   custom tokens extend the existing AnonymousToken and RememberMeToken.
