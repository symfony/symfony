UPGRADE FROM 4.x to 5.0
=======================

Cache
-----

 * Removed `CacheItem::getPreviousTags()`, use `CacheItem::getMetadata()` instead.

Config
------

 * Added the `getChildNodeDefinitions()` method to `ParentNodeDefinitionInterface`.
 * The `Processor` class has been made final

Console
-------

 * Removed the `setCrossingChar()` method in favor of the `setDefaultCrossingChar()` method in `TableStyle`.
 * Removed the `setHorizontalBorderChar()` method in favor of the `setDefaultCrossingChars()` method in `TableStyle`.
 * Removed the `getHorizontalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.
 * Removed the `setVerticalBorderChar()` method in favor of the `setVerticalBorderChars()` method in `TableStyle`.
 * Removed the `getVerticalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.

DependencyInjection
-------------------

 * Removed the `TypedReference::canBeAutoregistered()` and  `TypedReference::getRequiringClass()` methods.
 * Removed support for auto-discovered extension configuration class which does not implement `ConfigurationInterface`.

EventDispatcher
---------------

 * The `TraceableEventDispatcherInterface` has been removed.

FrameworkBundle
---------------

 * Removed support for `bundle:controller:action` and `service:action` syntaxes to reference controllers. Use `serviceOrFqcn::method`
   instead where `serviceOrFqcn` is either the service ID when using controllers as services or the FQCN of the controller.

   Before:

   ```yml
   bundle_controller:
       path: /
       defaults:
           _controller: FrameworkBundle:Redirect:redirect

   service_controller:
       path: /
       defaults:
           _controller: app.my_controller:myAction
   ```

   After:

   ```yml
   bundle_controller:
       path: /
       defaults:
           _controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController::redirectAction

   service_controller:
       path: /
       defaults:
           _controller: app.my_controller::myAction
   ```

 * Removed `Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser`.
 * Warming up a router in `RouterCacheWarmer` that does not implement the `WarmableInterface` is not supported anymore.
 * The `RequestDataCollector` class has been removed. Use the `Symfony\Component\HttpKernel\DataCollector\RequestDataCollector` class instead.

HttpFoundation
--------------

 * The `$size` argument of the `UploadedFile` constructor has been removed.
 * The `getClientSize()` method of the `UploadedFile` class has been removed.
 * The `getSession()` method of the `Request` class throws an exception when session is null.

Security
--------

 * The `ContextListener::setLogoutOnUserChange()` method has been removed.
 * The `Symfony\Component\Security\Core\User\AdvancedUserInterface` has been removed.
 * The `ExpressionVoter::addExpressionLanguageProvider()` method has been removed.
 * The `FirewallMapInterface::getListeners()` method must return an array of 3 elements,
   the 3rd one must be either a `LogoutListener` instance or `null`.
 * The `AuthenticationTrustResolver` constructor arguments have been removed.

SecurityBundle
--------------

 * The `logout_on_user_change` firewall option has been removed.
 * The `switch_user.stateless` firewall option has been removed.
 * The `SecurityUserValueResolver` class has been removed.
 * Passing a `FirewallConfig` instance as 3rd argument to  the `FirewallContext` constructor
   now throws a `\TypeError`, pass a `LogoutListener` instance instead.
 * The `security.authentication.trust_resolver.anonymous_class` parameter has been removed.
 * The `security.authentication.trust_resolver.rememberme_class` parameter has been removed.

Translation
-----------

 * The `FileDumper::setBackup()` method has been removed.
 * The `TranslationWriter::disableBackup()` method has been removed.

TwigBundle
----------

 * The default value (`false`) of the `twig.strict_variables` configuration option has been changed to `%kernel.debug%`.

Validator
--------

 * The `Email::__construct()` 'strict' property has been removed. Use 'mode'=>"strict" instead.
 * Calling `EmailValidator::__construct()` method with a boolean parameter has been removed, use `EmailValidator("strict")` instead.
 * Removed the `checkDNS` and `dnsMessage` options from the `Url` constraint.

Workflow
--------

 * The `DefinitionBuilder::reset()` method has been removed, use the `clear()` one instead.
 * `add` method has been removed use `addWorkflow` method in `Workflow\Registry` instead.
 * `SupportStrategyInterface` has been removed, use `WorkflowSupportStrategyInterface` instead.
 * `ClassInstanceSupportStrategy` has been removed, use `InstanceOfSupportStrategy` instead.
