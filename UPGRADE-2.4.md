UPGRADE FROM 2.3 to 2.4
=======================

Form
----

 * The constructor parameter `$precision` in `IntegerToLocalizedStringTransformer`
   is now ignored completely, because a precision does not make sense for
   integers.

Intl
----

 * A new method `getLocaleAliases()` was added to `LocaleBundleInterface`. If
   any of your classes implements this interface, you should add an implementation
   of this method.
