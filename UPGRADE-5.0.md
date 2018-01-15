UPGRADE FROM 4.x to 5.0
=======================

Config
------

 * Added the `getChildNodeDefinitions()` method to `ParentNodeDefinitionInterface`.

EventDispatcher
---------------

 * The `TraceableEventDispatcherInterface` has been removed.

HttpFoundation
--------------

 * The `$size` argument of the `UploadedFile` constructor has been removed.

 * The `getClientSize()` method of the `UploadedFile` class has been removed.

Security
--------

 * The `ContextListener::setLogoutOnUserChange()` method has been removed.

SecurityBundle
--------------

 * The `logout_on_user_change` firewall option has been removed.
 * The `SecurityUserValueResolver` class has been removed.

Translation
-----------

 * The `FileDumper::setBackup()` method has been removed.
 * The `TranslationWriter::disableBackup()` method has been removed.

Validator
--------

 * The `Email::__construct()` 'strict' property has been removed. Use 'mode'=>"strict" instead.
 * Calling `EmailValidator::__construct()` method with a boolean parameter has been removed, use `EmailValidator("strict")` instead.
 * Removed the `checkDNS` and `dnsMessage` options from the `Url` constraint.

Workflow
--------

 * `add` method has been removed use `addWorkflow` method in `Workflow\Registry` instead.
 * `SupportStrategyInterface` has been removed, use `WorkflowSupportStrategyInterface` instead.
 * `ClassInstanceSupportStrategy` has been removed, use `InstanceOfSupportStrategy` instead.
