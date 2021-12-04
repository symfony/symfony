UPGRADE FROM 6.1 to 6.2
=======================

Validator
---------

 * Deprecate the "loose" e-mail validation mode, use "html5" instead

Security
--------

 * Deprecate `RememberMeHandler` not implementing `getUserIdentifierForCookie()`; `AbstractRememberMeHandler` has a default implementation
 * Deprecate `RememberMeHandler` not implementing `getRememberMeDetails()`; `AbstractRememberMeHandler` has a default implementation
