CHANGELOG
=========

6.3
---

 * Allow escaping `.` and `[` with `\` in `PropertyPath`

6.2
---

 * Deprecate calling `PropertyAccessorBuilder::setCacheItemPool()` without arguments
 * Added method `isNullSafe()` to `PropertyPathInterface`, implementing the interface without implementing this method
   is deprecated
 * Add support for the null-coalesce operator in property paths

6.0
---

 * make `PropertyAccessor::__construct()` accept a combination of bitwise flags as first and second arguments

5.3.0
-----

 * deprecate passing a boolean as the second argument of `PropertyAccessor::__construct()`, expecting a combination of bitwise flags instead

5.2.0
-----

 * deprecated passing a boolean as the first argument of `PropertyAccessor::__construct()`, expecting a combination of bitwise flags instead
 * added the ability to disable usage of the magic `__get` & `__set` methods

5.1.0
-----

 * Added an `UninitializedPropertyException`
 * Linking to PropertyInfo extractor to remove a lot of duplicate code

4.4.0
-----

 * deprecated passing `null` as `$defaultLifetime` 2nd argument of `PropertyAccessor::createCache()` method,
   pass `0` instead

4.3.0
-----

 * added a `$throwExceptionOnInvalidPropertyPath` argument to the PropertyAccessor constructor.
 * added `enableExceptionOnInvalidPropertyPath()`, `disableExceptionOnInvalidPropertyPath()` and
   `isExceptionOnInvalidPropertyPath()` methods to `PropertyAccessorBuilder`

4.0.0
-----

 * removed the `StringUtil` class, use `Symfony\Component\Inflector\Inflector`

3.1.0
-----

 * deprecated the `StringUtil` class, use `Symfony\Component\Inflector\Inflector`
   instead

2.7.0
------

 * `UnexpectedTypeException` now expects three constructor arguments: The invalid property value,
   the `PropertyPathInterface` object and the current index of the property path.

2.5.0
------

 * allowed non alpha numeric characters in second level and deeper object properties names
 * [BC BREAK] when accessing an index on an object that does not implement
   ArrayAccess, a NoSuchIndexException is now thrown instead of the
   semantically wrong NoSuchPropertyException
 * [BC BREAK] added isReadable() and isWritable() to PropertyAccessorInterface

2.3.0
------

 * added PropertyAccessorBuilder, to enable or disable the support of "__call"
 * added support for "__call" in the PropertyAccessor (disabled by default)
 * [BC BREAK] changed PropertyAccessor to continue its search for a property or
   method even if a non-public match was found. Before, a PropertyAccessDeniedException
   was thrown in this case. Class PropertyAccessDeniedException was removed
   now.
 * deprecated PropertyAccess::getPropertyAccessor
 * added PropertyAccess::createPropertyAccessor and PropertyAccess::createPropertyAccessorBuilder
