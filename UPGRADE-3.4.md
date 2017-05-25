UPGRADE FROM 3.3 to 3.4
=======================

DependencyInjection
-------------------

  * Top-level anonymous services in XML are deprecated and will throw an exception in Symfony 4.0.

Finder
------

 * The `Symfony\Component\Finder\Iterator\FilterIterator` class has been
   deprecated and will be removed in 4.0 as it used to fix a bug which existed 
   before version 5.5.23/5.6.7.

Validator
---------

 * Not setting the `strict` option of the `Choice` constraint to `true` is
   deprecated and will throw an exception in Symfony 4.0.
