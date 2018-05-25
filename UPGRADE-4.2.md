UPGRADE FROM 4.1 to 4.2
=======================

Security
--------

 * Using the `has_role()` function in security expressions is deprecated, use the `is_granted()` function instead.
 * Not returning an array of 3 elements from `FirewallMapInterface::getListeners()` is deprecated, the 3rd element 
   must be an instance of `LogoutListener` or `null`.

SecurityBundle
--------------

 * Passing a `FirewallConfig` instance as 3rd argument to the `FirewallContext` constructor is deprecated, 
   pass a `LogoutListener` instance instead.
