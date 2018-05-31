UPGRADE FROM 4.0 to 4.1
=======================

Config
------

 * Implementing `ParentNodeDefinitionInterface` without the `getChildNodeDefinitions()` method
   is deprecated.

Console
-------

 * Deprecated the `setCrossingChar()` method in favor of the `setDefaultCrossingChar()` method in `TableStyle`.
 * The `Processor` class has been made final
 * Deprecated the `setHorizontalBorderChar()` method in favor of the `setDefaultCrossingChars()` method in `TableStyle`.
 * Deprecated the `getHorizontalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.
 * Deprecated the `setVerticalBorderChar()` method in favor of the `setVerticalBorderChars()` method in `TableStyle`.
 * Deprecated the `getVerticalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.
 * Added support for `iterable` messages in `write` and `writeln` methods of `Symfony\Component\Console\Output\OutputInterface`.
   If you have a custom implementation of the interface, you should make sure it works with iterable as well.

DependencyInjection
-------------------

 * Deprecated the `TypedReference::canBeAutoregistered()` and  `TypedReference::getRequiringClass()` methods.
 * Deprecated support for auto-discovered extension configuration class which does not implement `ConfigurationInterface`.

EventDispatcher
---------------

 * The `TraceableEventDispatcherInterface` has been deprecated.

Form
----

 * Deprecated the `ChoiceLoaderInterface` implementation in `CountryType`,
   `LanguageType`, `LocaleType` and `CurrencyType`, use the `choice_loader`
   option instead.

   Before:
   ```php
   class MyCountryType extends CountryType
   {
       public function loadChoiceList()
       {
           // override the method
       }
   }
   ```

   After:
   ```php
   class MyCountryType extends AbstractType
   {
       public function getParent()
       {
           return CountryType::class;
       }

       public function configureOptions(OptionsResolver $resolver)
       {
           $resolver->setDefault('choice_loader', ...); // override the option instead
       }
   }
   ```

 * Added `help` option to the form field. If you have custom Form extension for it, you should remove it.
   Also remove it from the custom form theme.

FrameworkBundle
---------------

 * Deprecated `bundle:controller:action` and `service:action` syntaxes to reference controllers. Use `serviceOrFqcn::method`
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

 * Deprecated `Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser`
 * Warming up a router in `RouterCacheWarmer` that does not implement the `WarmableInterface` is deprecated and will not be
   supported anymore in 5.0.
 * The `RequestDataCollector` class has been deprecated. Use the `Symfony\Component\HttpKernel\DataCollector\RequestDataCollector` class instead.

HttpFoundation
--------------

 * Passing the file size to the constructor of the `UploadedFile` class is deprecated.
 * The `getClientSize()` method of the `UploadedFile` class is deprecated. Use `getSize()` instead.
 * Deprecated `Symfony\Component\HttpFoundation\Request::getSession()` when no session has been set. Use `Symfony\Component\HttpFoundation\Request::hasSession()` instead.

Security
--------

 * The `ContextListener::setLogoutOnUserChange()` method is deprecated.
 * Using the `AdvancedUserInterface` is now deprecated. To use the existing
   functionality, create a custom user-checker based on the
   `Symfony\Component\Security\Core\User\UserChecker`.
 * `AuthenticationUtils::getLastUsername()` now always returns a string.
 * The `ExpressionVoter::addExpressionLanguageProvider()` method is deprecated. Register the provider directly on the injected ExpressionLanguage instance instead.

SecurityBundle
--------------

 * The `logout_on_user_change` firewall option is deprecated.
 * The `switch_user.stateless` firewall option is deprecated, use the `stateless` option instead.
 * The `SecurityUserValueResolver` class is deprecated, use
   `Symfony\Component\Security\Http\Controller\UserValueResolver` instead.

Serializer
----------

 * Decoding XML with `XmlEncoder` now ignores comment node types by default.

Translation
-----------

 * The `FileDumper::setBackup()` method is deprecated.
 * The `TranslationWriter::disableBackup()` method is deprecated.

TwigBundle
----------

 * Deprecated relying on the default value (`false`) of the `twig.strict_variables` configuration option. You should use `%kernel.debug%` explicitly instead, which will be the new default in 5.0.

Validator
--------

 * The `Email::__construct()` 'strict' property is deprecated. Use 'mode'=>"strict" instead.
 * Calling `EmailValidator::__construct()` method with a boolean parameter is deprecated, use `EmailValidator("strict")` instead.
 * Deprecated the `checkDNS` and `dnsMessage` options of the `Url` constraint.

Workflow
--------

 * Deprecated the `DefinitionBuilder::reset()` method, use the `clear()` one instead.
 * Deprecated the `add` method in favor of the `addWorkflow` method in `Workflow\Registry`.
 * Deprecated `SupportStrategyInterface` in favor of `WorkflowSupportStrategyInterface`.
 * Deprecated the class `ClassInstanceSupportStrategy` in favor of the class `InstanceOfSupportStrategy`.
 * Deprecated passing the workflow name as 4th parameter of `Event` constructor in favor of the workflow itself.
