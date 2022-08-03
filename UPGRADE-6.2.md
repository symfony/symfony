UPGRADE FROM 6.1 to 6.2
=======================

FrameworkBundle
---------------

 * Deprecate the `Symfony\Component\Serializer\Normalizer\ObjectNormalizer` and
   `Symfony\Component\Serializer\Normalizer\PropertyNormalizer` autowiring aliases, type-hint against
   `Symfony\Component\Serializer\Normalizer\NormalizerInterface` or implement `NormalizerAwareInterface` instead
 * Deprecate `AbstractController::renderForm()`, use `render()` instead

HttpFoundation
--------------

 * Deprecate `Request::getContentType()`, use `Request::getContentTypeFormat()` instead

Ldap
----

 * Deprecate `{username}` parameter use in favour of `{user_identifier}`

Mailer
------

 * Deprecate the `OhMySMTP` transport, use `MailPace` instead

Security
--------

 * Add maximum username length enforcement of 4096 characters in `UserBadge` to
   prevent [session storage flooding](https://symfony.com/blog/cve-2016-4423-large-username-storage-in-session)
 * Deprecate the `Symfony\Component\Security\Core\Security` class and service, use `Symfony\Bundle\SecurityBundle\Security\Security` instead
 * Passing empty username or password parameter when using `JsonLoginAuthenticator` is not supported anymore
 * Add `$lifetime` parameter to `LoginLinkHandlerInterface::createLoginLink()`

Validator
---------

 * Deprecate the `loose` e-mail validation mode, use `html5` instead
