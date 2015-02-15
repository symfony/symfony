UPGRADE FROM 2.3 to 2.4
=======================

Form
----

 * The constructor parameter `$precision` in `IntegerToLocalizedStringTransformer`
   is now ignored completely, because a precision does not make sense for
   integers.
