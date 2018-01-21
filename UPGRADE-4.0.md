UPGRADE FROM 3.x to 4.0
=======================

Symfony Framework
-----------------

The first step to upgrade a Symfony 3.x application to 4.x is to update the
file and directory structure of your application:

| Symfony 3.x                         | Symfony 4.x
| ----------------------------------- | --------------------------------
| `app/config/`                       | `config/`
| `app/config/*.yml`                  | `config/*.yaml` and `config/packages/*.yaml`
| `app/config/parameters.yml.dist`    | `config/services.yaml` and `.env.dist`
| `app/config/parameters.yml`         | `config/services.yaml` and `.env`
| `app/Resources/<BundleName>/views/` | `templates/bundles/<BundleName>/`
| `app/Resources/`                    | `src/Resources/`
| `app/Resources/assets/`             | `assets/`
| `app/Resources/translations/`       | `translations/`
| `app/Resources/views/`              | `templates/`
| `src/AppBundle/`                    | `src/`
| `var/logs/`                         | `var/log/`
| `web/`                              | `public/`
| `web/app.php`                       | `public/index.php`
| `web/app_dev.php`                   | `public/index.php`

Then, upgrade the contents of your console script and your front controller:

* `bin/console`: https://github.com/symfony/recipes/blob/master/symfony/console/3.3/bin/console
* `public/index.php`: https://github.com/symfony/recipes/blob/master/symfony/framework-bundle/3.3/public/index.php

Lastly, read the following article to add Symfony Flex to your application and
upgrade the configuration files: https://symfony.com/doc/current/setup/flex.html

If you use Symfony components instead of the whole framework, you can find below
the upgrading instructions for each individual bundle and component.

ClassLoader
-----------

 * The component has been removed. Use Composer instead.

Config
------

 * The protected `TreeBuilder::$builder` property has been removed.

Console
-------

 * Setting unknown style options is not supported anymore and throws an
   exception.

 * The `QuestionHelper::setInputStream()` method is removed. Use
   `StreamableInputInterface::setStream()` or `CommandTester::setInputs()`
   instead.

   Before:

   ```php
   $input = new ArrayInput();

   $questionHelper->setInputStream($stream);
   $questionHelper->ask($input, $output, $question);
   ```

   After:

   ```php
   $input = new ArrayInput();
   $input->setStream($stream);

   $questionHelper->ask($input, $output, $question);
   ```

   Before:

   ```php
   $commandTester = new CommandTester($command);

   $stream = fopen('php://memory', 'r+', false);
   fputs($stream, "AppBundle\nYes");
   rewind($stream);

   $command->getHelper('question')->setInputStream($stream);

   $commandTester->execute();
   ```

   After:

   ```php
   $commandTester = new CommandTester($command);

   $commandTester->setInputs(array('AppBundle', 'Yes'));

   $commandTester->execute();
   ```

 * The `console.exception` event and the related `ConsoleExceptionEvent` class have
   been removed in favor of the `console.error` event and the `ConsoleErrorEvent` class.

 * The `SymfonyQuestionHelper::ask` default validation has been removed in favor of `Question::setValidator`.

Debug
-----


 * The `ContextErrorException` class has been removed. Use `\ErrorException` instead.

 * `FlattenException::getTrace()` now returns additional type descriptions
   `integer` and `float`.

 * Support for stacked errors in the `ErrorHandler` has been removed

DependencyInjection
-------------------

 * Definitions and aliases are now private by default in 4.0. You should either use service injection
   or explicitly define your services as public if you really need to inject the container.

 * Relying on service auto-registration while autowiring is not supported anymore.
   Explicitly inject your dependencies or create services whose ids are
   their fully-qualified class name.

   Before:

   ```php
   namespace App\Controller;

   use App\Mailer;

   class DefaultController
   {
       public function __construct(Mailer $mailer) {
           // ...
       }

       // ...
   }
   ```
   ```yml
   services:
       App\Controller\DefaultController:
           autowire: true
   ```

   After:

   ```php
   // same PHP code
   ```
   ```yml
   services:
       App\Controller\DefaultController:
           autowire: true

       # or
       # App\Controller\DefaultController:
       #     arguments: { $mailer: "@App\Mailer" }

       App\Mailer:
           autowire: true
    ```

 * Autowiring services based on the types they implement is not supported anymore. Rename (or alias) your services to their FQCN id to make them autowirable.

 * `_defaults` and `_instanceof` are now reserved service names in Yaml configurations. Please rename any services with that names.

 * Non-numeric keys in methods and constructors arguments have never been supported and are now forbidden. Please remove them if you happen to have one.

 * Service names that start with an underscore are now reserved in Yaml files. Please rename any services with such names.

 * Autowiring-types have been removed, use aliases instead.

   Before:

   ```xml
   <service id="annotations.reader" class="Doctrine\Common\Annotations\AnnotationReader" public="false">
       <autowiring-type>Doctrine\Common\Annotations\Reader</autowiring-type>
   </service>
   ```

   After:

   ```xml
   <service id="annotations.reader" class="Doctrine\Common\Annotations\AnnotationReader" public="false" />
   <service id="Doctrine\Common\Annotations\Reader" alias="annotations.reader" public="false" />
   ```

 * Service identifiers and parameter names are now case sensitive.

 * The `Reference` and `Alias` classes do not make service identifiers lowercase anymore.

 * Using the `PhpDumper` with an uncompiled `ContainerBuilder` is not supported
   anymore.

 * Extending the containers generated by `PhpDumper` is not supported
   anymore.

 * The `DefinitionDecorator` class has been removed. Use the `ChildDefinition`
   class instead.

 * The `ResolveDefinitionTemplatesPass` class has been removed.
   Use the `ResolveChildDefinitionsPass` class instead.

 * Using unsupported configuration keys in YAML configuration files raises an
   exception.

 * Using unsupported options to configure service aliases raises an exception.

 * Setting or unsetting a service with the `Container::set()` method is
   no longer supported. Only synthetic services can be set or unset.

 * Checking the existence of a private service with the `Container::has()`
   method is no longer supported and will return `false`.

 * Requesting a private service with the `Container::get()` method is no longer
   supported.

 * The ``strict`` attribute in service arguments has been removed.
   The attribute is ignored since 3.0, so you can simply remove it.

 * Top-level anonymous services in XML are no longer supported.

 * The `ExtensionCompilerPass` has been moved to before-optimization passes with priority -1000.

DoctrineBridge
--------------

* The `Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandler` and
  `Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandlerSchema` have been removed. Use
  `Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler` instead.

EventDispatcher
---------------

 * The `ContainerAwareEventDispatcher` class has been removed.
   Use `EventDispatcher` with closure factories instead.

 * The `reset()` method has been added to `TraceableEventDispatcherInterface`.

ExpressionLanguage
------------------

 * The ability to pass a `ParserCacheInterface` instance to the `ExpressionLanguage`
   class has been removed. You should use the `CacheItemPoolInterface` interface
   instead.

Filesystem
----------

 * The `Symfony\Component\Filesystem\LockHandler` has been removed,
   use the `Symfony\Component\Lock\Store\FlockStore` class
   or  the `Symfony\Component\Lock\Store\FlockStore\SemaphoreStore` class directly instead.
 * Support for passing relative paths to `Filesystem::makePathRelative()` has been removed.

Finder
------

 * The `ExceptionInterface` has been removed.
 * The `Symfony\Component\Finder\Iterator\FilterIterator` class has been
   removed as it used to fix a bug which existed before version 5.5.23/5.6.7

Form
----

* The values of the `FormEvents::*` constants have been updated to match the
  constant names. You should only update your application if you relied on the
  constant values instead of their names.

 * The `choices_as_values` option of the `ChoiceType` has been removed.

 * Support for data objects that implements both `Traversable` and
   `ArrayAccess` in `ResizeFormListener::preSubmit` method has been removed.

 * Using callable strings as choice options in ChoiceType is not supported
   anymore in favor of passing PropertyPath instances.

   Before:

   ```php
   'choice_value' => new PropertyPath('range'),
   'choice_label' => 'strtoupper',
   ```

   After:

   ```php
   'choice_value' => 'range',
   'choice_label' => function ($choice) {
       return strtoupper($choice);
   },
   ```

 * Caching of the loaded `ChoiceListInterface` in the `LazyChoiceList` has been removed,
   it must be cached in the `ChoiceLoaderInterface` implementation instead.

 * Calling `isValid()` on a `Form` instance before submitting it is not supported
   anymore and raises an exception.

   Before:

   ```php
   if ($form->isValid()) {
       // ...
   }
   ```

   After:

   ```php
   if ($form->isSubmitted() && $form->isValid()) {
       // ...
   }
   ```

 * Using the "choices" option in ``CountryType``, ``CurrencyType``, ``LanguageType``,
   ``LocaleType``, and ``TimezoneType`` without overriding the ``choice_loader``
   option is now ignored.

   Before:
   ```php
   $builder->add('custom_locales', LocaleType::class, array(
       'choices' => $availableLocales,
   ));
   ```

   After:
   ```php
   $builder->add('custom_locales', LocaleType::class, array(
       'choices' => $availableLocales,
       'choice_loader' => null,
   ));
   // or
   $builder->add('custom_locales', LocaleType::class, array(
       'choice_loader' => new CallbackChoiceLoader(function () {
           return $this->getAvailableLocales();
       }),
   ));
   ```

 * Removed `ChoiceLoaderInterface` implementation in `TimezoneType`. Use the "choice_loader" option instead.

   Before:
   ```php
   class MyTimezoneType extends TimezoneType
   {
       public function loadChoices()
       {
           // override the method
       }
   }
   ```

   After:
   ```php
   class MyTimezoneType extends AbstractType
   {
       public function getParent()
       {
           return TimezoneType::class;
       }

       public function configureOptions(OptionsResolver $resolver)
       {
           $resolver->setDefault('choice_loader', ...); // override the option instead
       }
   }
   ```

 * `FormRendererInterface::setTheme` and `FormRendererEngineInterface::setTheme` have a new optional argument `$useDefaultThemes` with a default value set to `true`.

FrameworkBundle
---------------

 * The `session.use_strict_mode` option has been removed and strict mode is always enabled.

 * The `validator.mapping.cache.doctrine.apc` service has been removed.

 * The "framework.trusted_proxies" configuration option and the corresponding "kernel.trusted_proxies" parameter have been removed. Use the `Request::setTrustedProxies()` method in your front controller instead.

 * The default value of the `framework.workflows.[name].type` configuration options is now `state_machine`.

 * Support for absolute template paths has been removed.

 * The following form types registered as services have been removed; use their
   fully-qualified class name instead:

    - `"form.type.birthday"`
    - `"form.type.checkbox"`
    - `"form.type.collection"`
    - `"form.type.country"`
    - `"form.type.currency"`
    - `"form.type.date"`
    - `"form.type.datetime"`
    - `"form.type.email"`
    - `"form.type.file"`
    - `"form.type.hidden"`
    - `"form.type.integer"`
    - `"form.type.language"`
    - `"form.type.locale"`
    - `"form.type.money"`
    - `"form.type.number"`
    - `"form.type.password"`
    - `"form.type.percent"`
    - `"form.type.radio"`
    - `"form.type.range"`
    - `"form.type.repeated"`
    - `"form.type.search"`
    - `"form.type.textarea"`
    - `"form.type.text"`
    - `"form.type.time"`
    - `"form.type.timezone"`
    - `"form.type.url"`
    - `"form.type.button"`
    - `"form.type.submit"`
    - `"form.type.reset"`

 * The `framework.serializer.cache` option and the services
   `serializer.mapping.cache.apc` and `serializer.mapping.cache.doctrine.apc`
   have been removed. APCu should now be automatically used when available.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\CompilerDebugDumpPass` has been removed.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConsoleCommandPass` has been removed.
   Use `Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass` instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SerializerPass` class has been removed.
   Use the `Symfony\Component\Serializer\DependencyInjection\SerializerPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FormPass` class has been
   removed. Use the `Symfony\Component\Form\DependencyInjection\FormPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\EventListener\SessionListener` class has been removed.
   Use the `Symfony\Component\HttpKernel\EventListener\SessionListener` class instead.

 * The `Symfony\Bundle\FrameworkBundle\EventListener\TestSessionListener` class has been
   removed. Use the `Symfony\Component\HttpKernel\EventListener\TestSessionListener`
   class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConfigCachePass` class has been removed.
   Use tagged iterator arguments instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\PropertyInfoPass` class has been
   removed. Use the `Symfony\Component\PropertyInfo\DependencyInjection\PropertyInfoPass`
   class instead.

 * Class parameters related to routing have been removed
    * router.options.generator_class
    * router.options.generator_base_class
    * router.options.generator_dumper_class
    * router.options.matcher_class
    * router.options.matcher_base_class
    * router.options.matcher_dumper_class
    * router.options.matcher.cache_class
    * router.options.generator.cache_class

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ControllerArgumentValueResolverPass` class
   has been removed. Use the `Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass`
   class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RoutingResolverPass`
   class has been removed. Use the
   `Symfony\Component\Routing\DependencyInjection\RoutingResolverPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\Translation\Translator` constructor now takes the
   default locale as mandatory 3rd argument.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddValidatorInitializersPass` class has been
   removed. Use the `Symfony\Component\Validator\DependencyInjection\AddValidatorInitializersPass`
   class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddConstraintValidatorsPass` class has been
   removed. Use the `Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass`
   class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ValidateWorkflowsPass` class
   has been removed. Use the `Symfony\Component\Workflow\DependencyInjection\ValidateWorkflowsPass`
   class instead.

 * Using the `KERNEL_DIR` environment variable and the automatic guessing based
   on the `phpunit.xml` file location have been removed from the `KernelTestCase::getKernelClass()`
   method implementation. Set the `KERNEL_CLASS` environment variable to the
   fully-qualified class name of your Kernel or override the `KernelTestCase::createKernel()`
   or `KernelTestCase::getKernelClass()` method instead.

 * The methods `KernelTestCase::getPhpUnitXmlDir()` and `KernelTestCase::getPhpUnitCliConfigArgument()`
   have been removed.

 * The `Symfony\Bundle\FrameworkBundle\Validator\ConstraintValidatorFactory` class has been removed.
   Use `Symfony\Component\Validator\ContainerConstraintValidatorFactory` instead.

 * The `--no-prefix` option of the `translation:update` command has
   been removed.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheClearerPass` class has been removed.
   Use tagged iterator arguments instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass` class has been removed.
   Use tagged iterator arguments instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationDumperPass`
   class has been removed. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslationDumperPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationExtractorPass`
   class has been removed. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass`
   class has been removed. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslatorPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Reader\TranslationReader` class instead.

 * The `translation.loader` service has been removed.
   Use the `translation.reader` service instead.

 * `AssetsInstallCommand::__construct()` now requires an instance of
   `Symfony\Component\Filesystem\Filesystem` as first argument.

 * `CacheClearCommand::__construct()` now requires an instance of
   `Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface` as
    first argument.

 * `CachePoolClearCommand::__construct()` now requires an instance of
   `Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer` as
    first argument.

 * `EventDispatcherDebugCommand::__construct()` now requires an instance of
   `Symfony\Component\EventDispatcher\EventDispatcherInterface` as
    first argument.

 * `RouterDebugCommand::__construct()` now requires an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument.

 * `RouterMatchCommand::__construct()` now requires an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument.

 * `TranslationDebugCommand::__construct()` now requires an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument.

 * `TranslationUpdateCommand::__construct()` now requires an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument.

 * The `Symfony\Bundle\FrameworkBundle\Translation\PhpExtractor`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Extractor\PhpExtractor` class instead.

 * The `Symfony\Bundle\FrameworkBundle\Translation\PhpStringTokenParser`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Extractor\PhpStringTokenParser` class instead.

HttpFoundation
--------------

 * The `Request::setTrustedProxies()` method takes a new `$trustedHeaderSet` argument.
   See http://symfony.com/doc/current/components/http_foundation/trusting_proxies.html for more info.

 * The `Request::setTrustedHeaderName()` and `Request::getTrustedHeaderName()` methods have been removed.

 * Extending the following methods of `Response`
   is no longer possible (these methods are now `final`):

    - `setDate`/`getDate`
    - `setExpires`/`getExpires`
    - `setLastModified`/`getLastModified`
    - `setProtocolVersion`/`getProtocolVersion`
    - `setStatusCode`/`getStatusCode`
    - `setCharset`/`getCharset`
    - `setPrivate`/`setPublic`
    - `getAge`
    - `getMaxAge`/`setMaxAge`
    - `setSharedMaxAge`
    - `getTtl`/`setTtl`
    - `setClientTtl`
    - `getEtag`/`setEtag`
    - `hasVary`/`getVary`/`setVary`
    - `isInvalid`/`isSuccessful`/`isRedirection`/`isClientError`/`isServerError`
    - `isOk`/`isForbidden`/`isNotFound`/`isRedirect`/`isEmpty`

 * The ability to check only for cacheable HTTP methods using `Request::isMethodSafe()` is
   not supported anymore, use `Request::isMethodCacheable()` instead.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\WriteCheckSessionHandler` class has been
   removed. Implement `SessionUpdateTimestampHandlerInterface` or extend `AbstractSessionHandler` instead.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler` and
   `Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy` classes have been removed.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler` does not work with the legacy
   mongo extension anymore. It requires mongodb/mongodb package and ext-mongodb.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler` class has been removed.
   Use `Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler` instead.

HttpKernel
----------

 * Bundle inheritance has been removed.

 * Relying on convention-based commands discovery is not supported anymore.
   Use PSR-4 based service discovery instead.

   Before:

   ```yml
   # app/config/services.yml
   services:
       # ...

       # implicit registration of all commands in the `Command` folder
   ```

   After:

   ```yml
   # app/config/services.yml
   services:
       # ...

       # explicit commands registration
       AppBundle\Command\:
           resource: '../../src/AppBundle/Command/*'
           tags: ['console.command']
   ```

 * The `Extension::addClassesToCompile()` and `Extension::getClassesToCompile()` methods have been removed.

 * Possibility to pass non-scalar values as URI attributes to the ESI and SSI
   renderers has been removed. The inline fragment renderer should be used with
   non-scalar attributes.

 * The `ControllerResolver::getArguments()` method has been removed. If you
   have your own `ControllerResolverInterface` implementation, you should
   inject an `ArgumentResolverInterface` instance.

 * The `DataCollector::varToString()` method has been removed in favor of `cloneVar()`.

 * The `Psr6CacheClearer::addPool()` method has been removed. Pass an array of pools indexed
   by name to the constructor instead.

 * The `LazyLoadingFragmentHandler::addRendererService()` method has been removed.

 * The `X-Status-Code` header method of setting a custom status code in the
   response when handling exceptions has been removed. There is now a new
   `GetResponseForExceptionEvent::allowCustomResponseCode()` method instead,
   which will tell the Kernel to use the response code set on the event's
   response object.

 * The `Kernel::getEnvParameters()` method has been removed.

 * The `SYMFONY__` environment variables are no longer processed automatically
   by Symfony. Use the `%env()%` syntax to get the value of any environment
   variable from configuration files instead.

 * The `getCacheDir()` method of your kernel should not be called while building the container.
   Use the `%kernel.cache_dir%` parameter instead. Not doing so may break the `cache:clear` command.

 * The `Symfony\Component\HttpKernel\Config\EnvParametersResource` class has been removed.

 * The `reset()` method has been added to `Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface`.

 * The `clear()` method has been added to `Symfony\Component\HttpKernel\Log\DebugLoggerInterface`.

 * The `ChainCacheClearer::add()` method has been removed,
   inject the list of clearers as a constructor argument instead.

 * The `CacheWarmerAggregate::add()` and `setWarmers()` methods have been removed,
   inject the list of clearers as a constructor argument instead.

 * The `CacheWarmerAggregate` and `ChainCacheClearer` classes have been made final.

Ldap
----

 * The `RenameEntryInterface` has been removed, and merged with `EntryManagerInterface`

Process
-------

 * Passing a not existing working directory to the constructor of the `Symfony\Component\Process\Process` class is not supported anymore.

 * The `Symfony\Component\Process\ProcessBuilder` class has been removed,
   use the `Symfony\Component\Process\Process` class directly instead.

 * The `ProcessUtils::escapeArgument()` method has been removed, use a command line array or give env vars to the `Process::start/run()` method instead.

 * Environment variables are always inherited in sub-processes.

 * Configuring `proc_open()` options has been removed.

 * Configuring Windows and sigchild compatibility is not possible anymore - they are always enabled.

 * Extending `Process::run()`, `Process::mustRun()` and `Process::restart()` is
   not supported anymore.
   
 * The `getEnhanceWindowsCompatibility()` and `setEnhanceWindowsCompatibility()` methods of the `Process` class have been removed.

Profiler
--------

 * The `profiler.matcher` option has been removed.

ProxyManager
------------

 * The `ProxyDumper` class has been made final

Security
--------

 * The `RoleInterface` has been removed. Extend the `Symfony\Component\Security\Core\Role\Role`
   class instead.

 * The `LogoutUrlGenerator::registerListener()` method expects a 6th `string $context = null` argument.

 * The `AccessDecisionManager::setVoters()` method has been removed. Pass the
   voters to the constructor instead.

 * Support for defining voters that don't implement the `VoterInterface` has been removed.

 * Calling `ContextListener::setLogoutOnUserChange(false)` won't have any
   effect anymore.

 * Removed the HTTP digest authentication system. The `NonceExpiredException`,
   `DigestAuthenticationListener` and `DigestAuthenticationEntryPoint` classes
   have been removed. Use another authentication system like `http_basic` instead.

 * The `GuardAuthenticatorInterface` interface has been removed.
   Use `AuthenticatorInterface` instead.

SecurityBundle
--------------

 * The `FirewallContext::getContext()` method has been removed, use the `getListeners()` and/or `getExceptionListener()` method instead.

 * The `FirewallMap::$map` and `$container` properties have been removed.

 * The `UserPasswordEncoderCommand` class does not allow `null` as the first argument anymore.

 * `UserPasswordEncoderCommand` does not extend `ContainerAwareCommand` nor implement `ContainerAwareInterface` anymore.

 * `InitAclCommand` has been removed. Use `Symfony\Bundle\AclBundle\Command\InitAclCommand` instead

 * `SetAclCommand` has been removed. Use `Symfony\Bundle\AclBundle\Command\SetAclCommand` instead

 * The firewall option `logout_on_user_change` is now always true, which will
   trigger a logout if the user changes between requests.

 * Removed the HTTP digest authentication system. The `HttpDigestFactory` class
   has been removed. Use another authentication system like `http_basic` instead.

 * The `switch_user.stateless` option is now always true if the firewall is stateless.

 * Not configuring explicitly the provider on a firewall is ambiguous when there is more than one registered provider.
   The first configured provider is not used anymore and an exception is thrown instead.
   Explicitly configure the provider to use on your firewalls.

Serializer
----------

 * The ability to pass a Doctrine `Cache` instance to the `ClassMetadataFactory`
   class has been removed. You should use the `CacheClassMetadataFactory` class
   instead.

 * Not defining the 6th argument `$format = null` of the
   `AbstractNormalizer::instantiateObject()` method when overriding it is not
   supported anymore.

 * Extending `ChainDecoder`, `ChainEncoder`, `ArrayDenormalizer` is not supported
   anymore.

Translation
-----------

 * Removed the backup feature from the file dumper classes.

 * The default value of the `$readerServiceId` argument of `TranslatorPass::__construct()` has been changed to `"translation.reader"`.

 * Removed `Symfony\Component\Translation\Writer\TranslationWriter::writeTranslations`,
   use `Symfony\Component\Translation\Writer\TranslationWriter::write` instead.

 * Removed support for passing `Symfony\Component\Translation\MessageSelector` as a second argument to the
   `Translator::__construct()`. You should pass an instance of `Symfony\Component\Translation\Formatter\MessageFormatterInterface` instead.

TwigBundle
----------

* The `ContainerAwareRuntimeLoader` class has been removed. Use the
  Twig `Twig_ContainerRuntimeLoader` class instead.

 * Removed `DebugCommand` in favor of `Symfony\Bridge\Twig\Command\DebugCommand`.

 * Removed `ContainerAwareInterface` implementation in `Symfony\Bundle\TwigBundle\Command\LintCommand`.

TwigBridge
----------

 * removed the `Symfony\Bridge\Twig\Form\TwigRenderer` class, use the `FormRenderer`
   class from the Form component instead

 * Removed the possibility to inject the Form `TwigRenderer` into the `FormExtension`.
   Upgrade Twig to `^1.30`, inject the `Twig_Environment` into the `TwigRendererEngine` and load
   the `TwigRenderer` using the `Twig_FactoryRuntimeLoader` instead.

   Before:

   ```php
   use Symfony\Bridge\Twig\Extension\FormExtension;
   use Symfony\Bridge\Twig\Form\TwigRenderer;
   use Symfony\Bridge\Twig\Form\TwigRendererEngine;

   // ...
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'));
   $rendererEngine->setEnvironment($twig);
   $twig->addExtension(new FormExtension(new TwigRenderer($rendererEngine, $csrfTokenManager)));
   ```

   After:

   ```php
   $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig'), $twig);
   $twig->addRuntimeLoader(new \Twig_FactoryRuntimeLoader(array(
       TwigRenderer::class => function () use ($rendererEngine, $csrfTokenManager) {
           return new TwigRenderer($rendererEngine, $csrfTokenManager);
       },
   )));
   $twig->addExtension(new FormExtension());
   ```

 * Removed the `TwigRendererEngineInterface` interface.

 * The `TwigRendererEngine::setEnvironment()` method has been removed.
   Pass the Twig Environment as second argument of the constructor instead.

 * Removed `DebugCommand::set/getTwigEnvironment`. Pass an instance of
   `Twig\Environment` as first argument of the constructor instead.

 * Removed `LintCommand::set/getTwigEnvironment`. Pass an instance of
   `Twig\Environment` as first argument of the constructor instead.


Validator
---------

 * The default value of the `strict` option of the `Choice` constraint was changed
   to `true`. Using any other value will throw an exception.

 * The `DateTimeValidator::PATTERN` constant was removed.

 * `Tests\Constraints\AbstractConstraintValidatorTest` has been removed in
   favor of `Test\ConstraintValidatorTestCase`.

   Before:

   ```php
   // ...
   use Symfony\Component\Validator\Tests\Constraints\AbstractConstraintValidatorTest;

   class MyCustomValidatorTest extends AbstractConstraintValidatorTest
   {
       // ...
   }
   ```

   After:

   ```php
   // ...
   use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

   class MyCustomValidatorTest extends ConstraintValidatorTestCase
   {
       // ...
   }
   ```

 * Setting the `checkDNS` option of the `Url` constraint to `true` is dropped
   in favor of `Url::CHECK_DNS_TYPE_*` constants values.

   Before:

   ```php
   $constraint = new Url(['checkDNS' => true]);
   ```

   After:

   ```php
   $constraint = new Url(['checkDNS' => Url::CHECK_DNS_TYPE_ANY]);
   ```

VarDumper
---------

 * The `VarDumperTestTrait::assertDumpEquals()` method expects a 3rd `$context = null`
   argument and moves `$message = ''` argument at 4th position.

   Before:

   ```php
   VarDumperTestTrait::assertDumpEquals($dump, $data, $message = '');
   ```

   After:

   ```php
   VarDumperTestTrait::assertDumpEquals($dump, $data, $filter = 0, $message = '');
   ```

 * The `VarDumperTestTrait::assertDumpMatchesFormat()` method expects a 3rd `$context = null`
   argument and moves `$message = ''` argument at 4th position.

   Before:

   ```php
   VarDumperTestTrait::assertDumpMatchesFormat($dump, $data, $message = '');
   ```

   After:

   ```php
   VarDumperTestTrait::assertDumpMatchesFormat($dump, $data, $filter = 0, $message = '');
   ```

WebProfilerBundle
-----------------

 * Removed the `getTemplates()` method of the `TemplateManager` class in favor
   of the `getNames()` method

Workflow
--------

 * Removed class name support in `WorkflowRegistry::add()` as second parameter.

Yaml
----

 * Support for the `!str` tag was removed, use the `!!str` tag instead.

 * Starting an unquoted string with a question mark followed by a space
   throws a `ParseException`.

 * Removed support for implicitly parsing non-string mapping keys as strings.
   Mapping keys that are no strings will result in a `ParseException`. Use
   quotes to opt-in for keys to be parsed as strings.

   Before:

   ```php
   $yaml = <<<YAML
   null: null key
   true: boolean true
   2.0: float key
   YAML;

   Yaml::parse($yaml);
   ```

   After:

   ```php

   $yaml = <<<YAML
   "null": null key
   "true": boolean true
   "2.0": float key
   YAML;

   Yaml::parse($yaml);
   ```

 * Removed the `Yaml::PARSE_KEYS_AS_STRINGS` flag.

   Before:

   ```php
   $yaml = <<<YAML
   null: null key
   true: boolean true
   2.0: float key
   YAML;

   Yaml::parse($yaml, Yaml::PARSE_KEYS_AS_STRINGS);
   ```

   After:

   ```php
   $yaml = <<<YAML
   "null": null key
   "true": boolean true
   "2.0": float key
   YAML;

   Yaml::parse($yaml);
   ```

 * Omitting the key of a mapping is not supported anymore and throws a `ParseException`.

 * Mappings with a colon (`:`) that is not followed by a whitespace are not
   supported anymore and lead to a `ParseException`(e.g. `foo:bar` must be
   `foo: bar`).

 * Starting an unquoted string with `%` leads to a `ParseException`.

 * The `Dumper::setIndentation()` method was removed. Pass the indentation
   level to the constructor instead.

 * Removed support for passing `true`/`false` as the second argument to the
   `parse()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Removed support for passing `true`/`false` as the third argument to the
   `parse()` method to toggle object support.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT);
   ```

 * Removed support for passing `true`/`false` as the fourth argument to the
   `parse()` method to parse objects as maps.

   Before:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', false, false, true);
   ```

   After:

   ```php
   Yaml::parse('{ "foo": "bar", "fiz": "cat" }', Yaml::PARSE_OBJECT_FOR_MAP);
   ```

 * Removed support for passing `true`/`false` as the fourth argument to the
   `dump()` method to trigger exceptions when an invalid type was passed.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
   ```

 * Removed support for passing `true`/`false` as the fifth argument to the
   `dump()` method to toggle object support.

   Before:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, true);
   ```

   After:

   ```php
   Yaml::dump(array('foo' => new A(), 'bar' => 1), 0, 0, false, Yaml::DUMP_OBJECT);
   ```

 * The `!!php/object` tag to indicate dumped PHP objects was removed in favor of
   the `!php/object` tag.

 * Duplicate mapping keys lead to a `ParseException`.

 * The constructor arguments `$offset`, `$totalNumberOfLines` and
   `$skippedLineNumbers` of the `Parser` class were removed.

 * The behavior of the non-specific tag `!` is changed and now forces
   non-evaluating your values.

 * The `!php/object:` tag was removed in favor of the `!php/object` tag (without
   the colon).

 * The `!php/const:` tag was removed in favor of the `!php/const` tag (without
   the colon).

   Before:

   ```yml
   !php/const:PHP_INT_MAX
   ```

   After:

   ```yml
   !php/const PHP_INT_MAX
   ```
