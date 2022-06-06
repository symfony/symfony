UPGRADE FROM 6.1 to 6.2
=======================

Security
--------

 * Add maximum username length enforcement of 4096 characters in `UserBadge` to
   prevent [session storage flooding](https://symfony.com/blog/cve-2016-4423-large-username-storage-in-session)
 * Deprecate the `Symfony\Component\Security\Core\Security` class and service, use `Symfony\Bundle\SecurityBundle\Security\Security` instead

Validator
---------

 * Deprecate the `loose` e-mail validation mode, use `html5` instead
