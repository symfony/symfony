UPGRADE FROM 6.3 to 6.4
=======================

Filesystem
----------

 * Deprecate calling `Filesystem::mirror()` with option `copy_on_windows`, use option `follow_symlinks` instead.

HttpFoundation
--------------

 * Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`
