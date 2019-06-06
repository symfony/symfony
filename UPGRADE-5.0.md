UPGRADE FROM 4.x to 5.0
=======================

BrowserKit
----------

 * The `Client::submit()` method has a new `$serverParameters` argument.

Cache
-----

 * Removed `CacheItem::getPreviousTags()`, use `CacheItem::getMetadata()` instead.

Config
------

 * Dropped support for constructing a `TreeBuilder` without passing root node information.
 * Added the `getChildNodeDefinitions()` method to `ParentNodeDefinitionInterface`.
 * The `Processor` class has been made final
 * Removed `FileLoaderLoadException`, use `LoaderLoadException` instead.

Console
-------

 * Removed the `setCrossingChar()` method in favor of the `setDefaultCrossingChar()` method in `TableStyle`.
 * Removed the `setHorizontalBorderChar()` method in favor of the `setDefaultCrossingChars()` method in `TableStyle`.
 * Removed the `getHorizontalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.
 * Removed the `setVerticalBorderChar()` method in favor of the `setVerticalBorderChars()` method in `TableStyle`.
 * Removed the `getVerticalBorderChar()` method in favor of the `getBorderChars()` method in `TableStyle`.
 * The `ProcessHelper::run()` method takes the command as an array of arguments.

   Before:
   ```php
   $processHelper->run($output, 'ls -l');
   ```

   After:
   ```php
   $processHelper->run($output, array('ls', '-l'));

   // alternatively, when a shell wrapper is required
   $processHelper->run($output, Process::fromShellCommandline('ls -l'));
   ```

DependencyInjection
-------------------

 * Removed the `TypedReference::canBeAutoregistered()` and  `TypedReference::getRequiringClass()` methods.
 * Removed support for auto-discovered extension configuration class which does not implement `ConfigurationInterface`.

DoctrineBridge
--------------

 * Deprecated injecting `ClassMetadataFactory` in `DoctrineExtractor`, an instance of `EntityManagerInterface` should be
   injected instead

DomCrawler
----------

 * The `Crawler::children()` method has a new `$selector` argument.

EventDispatcher
---------------

 * The `TraceableEventDispatcherInterface` has been removed.

Finder
------

 * The `Finder::sortByName()` method has a new `$useNaturalSort` argument.

Form
----

 * The `getExtendedType()` method was removed from the `FormTypeExtensionInterface`. It is replaced by the the static
   `getExtendedTypes()` method which must return an iterable of extended types.

   Before:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public function getExtendedType()
       {
           return FormType::class;
       }

       // ...
   }
   ```

   After:

   ```php
   class FooTypeExtension extends AbstractTypeExtension
   {
       public static function getExtendedTypes(): iterable
       {
           return array(FormType::class);
       }

       // ...
   }
   ```
 * The `scale` option was removed from the `IntegerType`.
 * The `$scale` argument of the `IntegerToLocalizedStringTransformer` was removed.
 * Calling `FormRenderer::searchAndRenderBlock` for fields which were already rendered
   throws an exception instead of returning empty strings:

   Before:
   ```twig
   {% for field in fieldsWithPotentialDuplicates %}
      {{ form_widget(field) }}
   {% endfor %}
   ```

   After:
   ```twig
   {% for field in fieldsWithPotentialDuplicates if not field.rendered %}
      {{ form_widget(field) }}
   {% endfor %}
   ```

 * The `regions` option was removed from the `TimezoneType`.

FrameworkBundle
---------------

 * Removed support for `bundle:controller:action` syntax to reference controllers. Use `serviceOrFqcn::method`
   instead where `serviceOrFqcn` is either the service ID when using controllers as services or the FQCN of the controller.

   Before:

   ```yml
   bundle_controller:
       path: /
       defaults:
           _controller: FrameworkBundle:Redirect:redirect
   ```

   After:

   ```yml
   bundle_controller:
       path: /
       defaults:
           _controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController::redirectAction
   ```

 * Removed `Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser`.
 * Warming up a router in `RouterCacheWarmer` that does not implement the `WarmableInterface` is not supported anymore.
 * The `RequestDataCollector` class has been removed. Use the `Symfony\Component\HttpKernel\DataCollector\RequestDataCollector` class instead.
 * Removed `Symfony\Bundle\FrameworkBundle\Controller\Controller`. Use `Symfony\Bundle\FrameworkBundle\Controller\AbstractController` instead.
 * Added support for the SameSite attribute for session cookies. It is highly recommended to set this setting (`framework.session.cookie_samesite`) to `lax` for increased security against CSRF attacks.
 * The `ContainerAwareCommand` class has been removed, use `Symfony\Component\Console\Command\Command`
   with dependency injection instead.
 * The `Templating\Helper\TranslatorHelper::transChoice()` method has been removed, use the `trans()` one instead with a `%count%` parameter.
 * Removed support for legacy translations directories `src/Resources/translations/` and `src/Resources/<BundleName>/translations/`, use `translations/` instead.
 * Support for the legacy directory structure in `translation:update` and `debug:translation` commands has been removed.

HttpFoundation
--------------

 * The `$size` argument of the `UploadedFile` constructor has been removed.
 * The `getClientSize()` method of the `UploadedFile` class has been removed.
 * The `getSession()` method of the `Request` class throws an exception when session is null.
 * The default value of the "$secure" and "$samesite" arguments of Cookie's constructor
   changed respectively from "false" to "null" and from "null" to "lax".

HttpKernel
----------

 * The `Kernel::getRootDir()` and the `kernel.root_dir` parameter have been removed
 * The `KernelInterface::getName()` and the `kernel.name` parameter have been removed
 * Removed the first and second constructor argument of `ConfigDataCollector`
 * Removed `ConfigDataCollector::getApplicationName()` 
 * Removed `ConfigDataCollector::getApplicationVersion()`

Monolog
-------

 * The methods `DebugProcessor::getLogs()`, `DebugProcessor::countErrors()`, `Logger::getLogs()` and `Logger::countErrors()` have a new `$request` argument.

Process
-------

 * Removed the `Process::setCommandline()` and the `PhpProcess::setPhpBinary()` methods.
 * Commands must be defined as arrays when creating a `Process` instance.

   Before:
   ```php
   $process = new Process('ls -l');
   ```

   After:
   ```php
   $process = new Process(array('ls', '-l'));

   // alternatively, when a shell wrapper is required
   $process = Process::fromShellCommandline('ls -l');
   ```

Security
--------

 * The `ContextListener::setLogoutOnUserChange()` method has been removed.
 * The `Symfony\Component\Security\Core\User\AdvancedUserInterface` has been removed.
 * The `ExpressionVoter::addExpressionLanguageProvider()` method has been removed.
 * The `FirewallMapInterface::getListeners()` method must return an array of 3 elements,
   the 3rd one must be either a `LogoutListener` instance or `null`.
 * The `AuthenticationTrustResolver` constructor arguments have been removed.
 * A user object that is not an instance of `UserInterface` cannot be accessed from `Security::getUser()` anymore and returns `null` instead.
 * `SimpleAuthenticatorInterface`, `SimpleFormAuthenticatorInterface`, `SimplePreAuthenticatorInterface`,
   `SimpleAuthenticationProvider`, `SimpleAuthenticationHandler`, `SimpleFormAuthenticationListener` and
   `SimplePreAuthenticationListener` have been removed. Use Guard instead.

SecurityBundle
--------------

 * The `logout_on_user_change` firewall option has been removed.
 * The `switch_user.stateless` firewall option has been removed.
 * The `SecurityUserValueResolver` class has been removed.
 * Passing a `FirewallConfig` instance as 3rd argument to  the `FirewallContext` constructor
   now throws a `\TypeError`, pass a `LogoutListener` instance instead.
 * The `security.authentication.trust_resolver.anonymous_class` parameter has been removed.
 * The `security.authentication.trust_resolver.rememberme_class` parameter has been removed.
 * The `simple_form` and `simple_preauth` authentication listeners have been removed,
   use Guard instead.
 * The `SimpleFormFactory` and `SimplePreAuthenticationFactory` classes have been removed,
   use Guard instead.

Serializer
----------

 * The `AbstractNormalizer::handleCircularReference()` method has two new `$format` and `$context` arguments.

Translation
-----------

 * The `FileDumper::setBackup()` method has been removed.
 * The `TranslationWriter::disableBackup()` method has been removed.
 * The `TranslatorInterface` has been removed in favor of `Symfony\Contracts\Translation\TranslatorInterface`
 * The `MessageSelector`, `Interval` and `PluralizationRules` classes have been removed, use `IdentityTranslator` instead
 * The `Translator::getFallbackLocales()` and `TranslationDataCollector::getFallbackLocales()` method are now internal
 * The `Translator::transChoice()` method has been removed in favor of using `Translator::trans()` with "%count%" as the parameter driving plurals

TwigBundle
----------

 * The default value (`false`) of the `twig.strict_variables` configuration option has been changed to `%kernel.debug%`.
 * The `transchoice` tag and filter have been removed, use the `trans` ones instead with a `%count%` parameter.
 * Removed support for legacy templates directories `src/Resources/views/` and `src/Resources/<BundleName>/views/`, use `templates/` and `templates/bundles/<BundleName>/` instead.

Validator
--------

 * The `checkMX` and `checkHost` options of the `Email` constraint were removed
 * The `Email::__construct()` 'strict' property has been removed. Use 'mode'=>"strict" instead.
 * Calling `EmailValidator::__construct()` method with a boolean parameter has been removed, use `EmailValidator("strict")` instead.
 * Removed the `checkDNS` and `dnsMessage` options from the `Url` constraint.
 * The component is now decoupled from `symfony/translation` and uses `Symfony\Contracts\Translation\TranslatorInterface` instead
 * The `ValidatorBuilderInterface` has been removed
 * Removed support for validating instances of `\DateTimeInterface` in `DateTimeValidator`, `DateValidator` and `TimeValidator`. Use `Type` instead or remove the constraint if the underlying model is type hinted to `\DateTimeInterface` already.
 * The `symfony/intl` component is now required for using the `Bic`, `Country`, `Currency`, `Language` and `Locale` constraints
 * The `egulias/email-validator` component is now required for using the `Email` constraint in strict mode
 * The `symfony/expression-language` component is now required for using the `Expression` constraint

Workflow
--------

 * The `DefinitionBuilder::reset()` method has been removed, use the `clear()` one instead.
 * `add` method has been removed use `addWorkflow` method in `Workflow\Registry` instead.
 * `SupportStrategyInterface` has been removed, use `WorkflowSupportStrategyInterface` instead.
 * `ClassInstanceSupportStrategy` has been removed, use `InstanceOfSupportStrategy` instead.
