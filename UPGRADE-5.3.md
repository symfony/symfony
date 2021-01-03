UPGRADE FROM 5.2 to 5.3
=======================

Asset
-----

 * Deprecated `RemoteJsonManifestVersionStrategy`, use `JsonManifestVersionStrategy` instead.

DomCrawler
----------

* Deprecated the `parents()` method, use `ancestors()` instead.

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

Messenger
---------

* Deprecated the `prefetch_count` parameter in the AMQP bridge, it has no effect and will be removed in Symfony 6.0.

Notifier
-------

* Changed the return type of `Symfony\Component\Notifier\Transport\AbstractTransportFactory::getEndpoint()` from `?string` to `string`

PhpunitBridge
-------------

 * Deprecated the `SetUpTearDownTrait` trait, use original methods with "void" return typehint.

PropertyInfo
------------

* Deprecated the `Type::getCollectionKeyType()` and `Type::getCollectionValueType()` methods, use `Type::getCollectionKeyTypes()` and `Type::getCollectionValueTypes()` instead.

Security
--------

 * Deprecated voters that do not return a valid decision when calling the `vote` method.

Serializer
----------

 * Deprecated `ArrayDenormalizer::setSerializer()`, call `setDenormalizer()` instead.
