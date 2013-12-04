UPGRADE FROM 2.3 to 2.4
=======================

Note: This guide describes only mandatory changes to upgrade to a certain version. If you upgrade
other things not listed here you should look into those guides. You should take these instructions
as last warnings. There could be optional things available already in this new version not noted here
however they are not deprecated yet and should be working correctly.

Form
----

 * The constructor parameter `$precision` in `IntegerToLocalizedStringTransformer`
   is now ignored completely, because a precision does not make sense for
   integers.
