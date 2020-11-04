UPGRADE FROM 5.2 to 5.3
=======================

DependencyInjection
-------------------

 * The signature of `ContainerAwareInterface::setContainer()` has been updated to `setContainer(?ContainerInterface $container)`. When calling an implementation of this interface, explicitly pass `null` if you want to unset the container.
 * Calling `ContainerAwareTrait::setContainer()` without arguments is deprecated. Please explicitly pass `null` if you want to unset the container.

Security
-------------------

 * The signature of `TokenStorageInterface::setToken()` has been updated to `setToken(?TokenInterface $token)`. When calling an implementation of this interface, explicitly pass `null` if you want to unset the token.
 * Calling `TokenStorage::setToken()` or `UsageTrackingTokenStorage::setToken()` without arguments is deprecated. Please explicitly pass `null` if you want to unset the token.
