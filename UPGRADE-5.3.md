UPGRADE FROM 5.2 to 5.3
=======================

Form
----

 * Changed `$forms` parameter type of the `DataMapperInterface::mapDataToForms()` method from `iterable` to `\Traversable`.
 * Changed `$forms` parameter type of the `DataMapperInterface::mapFormsToData()` method from `iterable` to `\Traversable`.
 * Deprecated passing an array as the second argument of the `DataMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `DataMapper::mapFormsToData()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the second argument of the `CheckboxListMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `CheckboxListMapper::mapFormsToData()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the second argument of the `RadioListMapper::mapDataToForms()` method, pass `\Traversable` instead.
 * Deprecated passing an array as the first argument of the `RadioListMapper::mapFormsToData()` method, pass `\Traversable` instead.

HttpKernel
----------

 * Marked the class `Symfony\Component\HttpKernel\EventListener\DebugHandlersListener` as internal

PhpunitBridge
-------------

 * Deprecated the `SetUpTearDownTrait` trait, use original methods with "void" return typehint.

Security
--------

 * Deprecated voters that do not return a valid decision when calling the `vote` method.
