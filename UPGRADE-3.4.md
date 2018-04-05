UPGRADE FROM 3.3 to 3.4
=======================

Config
------

 * The protected `TreeBuilder::$builder` property is deprecated and will be removed in 4.0.

DependencyInjection
-------------------

 * Definitions and aliases will be made private by default in 4.0. You should either use service injection
   or explicitly define your services as public if you really need to inject the container.

 * Relying on service auto-registration while autowiring is deprecated and won't be supported
   in Symfony 4.0. Explicitly inject your dependencies or create services
   whose ids are their fully-qualified class name.

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

 * Autowiring services based on the types they implement is deprecated and will not be supported anymore in Symfony 4.0
   where it will only match an alias or a service id that matches then given FQCN. You can opt in the behavior of Symfony
   4 by the enabling the `container.autowiring.strict_mode` parameter:

   ```yml
   parameters:
       container.autowiring.strict_mode: true
   ```

 * Top-level anonymous services in XML are deprecated and will throw an exception in Symfony 4.0.

 * Case insensitivity of parameter names is deprecated and will be removed in 4.0.

 * The `ResolveDefinitionTemplatesPass` class is deprecated and will be removed in 4.0.
   Use the `ResolveChildDefinitionsPass` class instead.

 * Unless you're using a custom autoloader, you should enable the `container.dumper.inline_class_loader`
   parameter. This can drastically improve DX by reducing the time to load classes
   when the `DebugClassLoader` is enabled. If you're using `FrameworkBundle`, this
   performance improvement will also impact the "dev" environment:

   ```yml
   parameters:
       container.dumper.inline_class_loader: true
   ```

Debug
-----

 * Support for stacked errors in the `ErrorHandler` is deprecated and will be removed in Symfony 4.0.

DoctrineBridge
--------------

* Deprecated `Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandler` and
  `Symfony\Bridge\Doctrine\HttpFoundation\DbalSessionHandlerSchema`. Use
  `Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler` instead.

EventDispatcher
---------------

 * Implementing `TraceableEventDispatcherInterface` without the `reset()` method
   is deprecated and will be unsupported in 4.0.

Filesystem
----------

 * The `Symfony\Component\Filesystem\LockHandler` class has been deprecated,
   use the `Symfony\Component\Lock\Store\FlockStore` class
   or the `Symfony\Component\Lock\Store\FlockStore\SemaphoreStore` class directly instead.
 * Support for passing relative paths to `Filesystem::makePathRelative()` is deprecated and will be removed in 4.0.

Finder
------

 * The `Symfony\Component\Finder\Iterator\FilterIterator` class has been
   deprecated and will be removed in 4.0 as it used to fix a bug which existed
   before version 5.5.23/5.6.7.

Form
----

 * Deprecated `ChoiceLoaderInterface` implementation in `TimezoneType`. Use the "choice_loader" option instead.

   Before:
   ```php
   class MyTimezoneType extends TimezoneType
   {
       public function loadChoiceList()
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

FrameworkBundle
---------------

 * The `session.use_strict_mode` option has been deprecated and is enabled by default.

 * The `cache:clear` command doesn't clear "app" PSR-6 cache pools anymore,
   but still clears "system" ones.
   Use the `cache:pool:clear` command to clear "app" pools instead.

 * The `doctrine/cache` dependency has been removed; require it via `composer
   require doctrine/cache` if you are using Doctrine cache in your project.

 * The `validator.mapping.cache.doctrine.apc` service has been deprecated.

 * The `symfony/stopwatch` dependency has been removed, require it via `composer
   require symfony/stopwatch` in your `dev` environment.

 * Using the `KERNEL_DIR` environment variable or the automatic guessing based
   on the `phpunit.xml` / `phpunit.xml.dist` file location is deprecated since 3.4.
   Set the `KERNEL_CLASS` environment variable to the fully-qualified class name
   of your Kernel instead. Not setting the `KERNEL_CLASS` environment variable
   will throw an exception on 4.0 unless you override the `KernelTestCase::createKernel()`
   or `KernelTestCase::getKernelClass()` method.

 * The `KernelTestCase::getPhpUnitXmlDir()` and `KernelTestCase::getPhpUnitCliConfigArgument()`
   methods are deprecated since 3.4 and will be removed in 4.0.

 * The `--no-prefix` option of the `translation:update` command is deprecated and
   will be removed in 4.0. Use the `--prefix` option with an empty string as value
   instead (e.g. `--prefix=""`)

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheClearerPass`
   class has been deprecated and will be removed in 4.0. Use tagged iterator arguments instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass`
   class has been deprecated and will be removed in 4.0. Use tagged iterator arguments instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationDumperPass`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslationDumperPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationExtractorPass`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\DependencyInjection\TranslatorPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Reader\TranslationReader` class instead.

 * The `translation.loader` service has been deprecated and will be removed in 4.0.
   Use the `translation.reader` service instead..

 * `AssetsInstallCommand::__construct()` now takes an instance of
   `Symfony\Component\Filesystem\Filesystem` as first argument.
   Not passing it is deprecated and will throw a `TypeError` in 4.0.

 * `CacheClearCommand::__construct()` now takes an instance of
   `Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `CachePoolClearCommand::__construct()` now takes an instance of
   `Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `EventDispatcherDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\EventDispatcher\EventDispatcherInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `RouterDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `RouterMatchCommand::__construct()` now takes an instance of
   `Symfony\Component\Routing\RouterInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `TranslationDebugCommand::__construct()` now takes an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `TranslationUpdateCommand::__construct()` now takes an instance of
   `Symfony\Component\Translation\TranslatorInterface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `AssetsInstallCommand`, `CacheClearCommand`, `CachePoolClearCommand`,
   `EventDispatcherDebugCommand`, `RouterDebugCommand`, `RouterMatchCommand`,
   `TranslationDebugCommand`, `TranslationUpdateCommand`, `XliffLintCommand`
    and `YamlLintCommand` classes have been marked as final

 * The `Symfony\Bundle\FrameworkBundle\Translation\PhpExtractor`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Extractor\PhpExtractor` class instead.

 * The `Symfony\Bundle\FrameworkBundle\Translation\PhpStringTokenParser`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\Translation\Extractor\PhpStringTokenParser` class instead.

HttpFoundation
--------------

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler`
   class has been deprecated and will be removed in 4.0. Use the `\SessionHandler` class instead.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\WriteCheckSessionHandler` class has been
   deprecated and will be removed in 4.0. Implement `SessionUpdateTimestampHandlerInterface` or
   extend `AbstractSessionHandler` instead.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy` class has been
   deprecated and will be removed in 4.0. Use your `\SessionHandlerInterface` implementation directly.

 * Using `Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler` with the legacy mongo extension
   has been deprecated and will be removed in 4.0. Use it with the mongodb/mongodb package and ext-mongodb instead.

 * The `Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler` class has been deprecated and
   will be removed in 4.0. Use `Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcachedSessionHandler` instead.

HttpKernel
----------

 * Bundle inheritance has been deprecated.

 * Relying on convention-based commands discovery has been deprecated and
   won't be supported in 4.0. Use PSR-4 based service discovery instead.

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

 * The `getCacheDir()` method of your kernel should not be called while building the container.
   Use the `%kernel.cache_dir%` parameter instead. Not doing so may break the `cache:clear` command.

 * The `Symfony\Component\HttpKernel\Config\EnvParametersResource` class has been deprecated and will be removed in 4.0.

 * Implementing `DataCollectorInterface` without a `reset()` method has been deprecated and will be unsupported in 4.0.

 * Implementing `DebugLoggerInterface` without a `clear()` method has been deprecated and will be unsupported in 4.0.

 * The `ChainCacheClearer::add()` method has been deprecated and will be removed in 4.0,
   inject the list of clearers as a constructor argument instead.

 * The `CacheWarmerAggregate::add()` and `setWarmers()` methods have been deprecated and will be removed in 4.0,
   inject the list of clearers as a constructor argument instead.

 * The `CacheWarmerAggregate` and `ChainCacheClearer` classes have been made final.

Process
-------

 * The `Symfony\Component\Process\ProcessBuilder` class has been deprecated,
   use the `Symfony\Component\Process\Process` class directly instead.

 * Calling `Process::start()` without setting a valid working directory (via `setWorkingDirectory()` or constructor) beforehand is deprecated and will throw an exception in 4.0.

Profiler
--------

 * The `profiler.matcher` option has been deprecated.

Security
--------

 * Deprecated the HTTP digest authentication: `NonceExpiredException`,
   `DigestAuthenticationListener` and `DigestAuthenticationEntryPoint` will be
   removed in 4.0. Use another authentication system like `http_basic` instead.

 * The `GuardAuthenticatorInterface` has been deprecated and will be removed in 4.0.
   Use `AuthenticatorInterface` instead.

SecurityBundle
--------------

 * Using voters that do not implement the `VoterInterface`is now deprecated in
   the `AccessDecisionManager` and this functionality will be removed in 4.0.

 * `FirewallContext::getListeners()` now returns `\Traversable|array`

 * `InitAclCommand::__construct()` now takes an instance of
   `Doctrine\DBAL\Connection`  as first argument. Not passing it is
    deprecated and will throw a `TypeError` in 4.0.

 * The `acl:set` command has been deprecated along with the `SetAclCommand` class,
   both will be removed in 4.0. Install symfony/acl-bundle instead

 * The `init:acl` command has been deprecated along with the `InitAclCommand` class,
   both will be removed in 4.0. Install symfony/acl-bundle and use `acl:init` instead

 * Added `logout_on_user_change` to the firewall options. This config item will
   trigger a logout when the user has changed. Should be set to true to avoid
   deprecations in the configuration.

 * Deprecated the HTTP digest authentication: `HttpDigestFactory` will be removed in 4.0.
   Use another authentication system like `http_basic` instead.

 * Deprecated setting the `switch_user.stateless` option to false when the firewall is `stateless`.
   Setting it to false will have no effect in 4.0.

 * Not configuring explicitly the provider on a firewall is ambiguous when there is more than one registered provider.
   Using the first configured provider is deprecated since 3.4 and will throw an exception on 4.0.
   Explicitly configure the provider to use on your firewalls.

Translation
-----------

 * `Symfony\Component\Translation\Writer\TranslationWriter::writeTranslations` has been deprecated
   and will be removed in 4.0, use `Symfony\Component\Translation\Writer\TranslationWriter::write`
   instead.

 * Passing a `Symfony\Component\Translation\MessageSelector` to `Translator` has been
   deprecated. You should pass a message formatter instead

   Before:

   ```php
   use Symfony\Component\Translation\Translator;
   use Symfony\Component\Translation\MessageSelector;

   $translator = new Translator('fr_FR', new MessageSelector());
   ```

   After:

   ```php
   use Symfony\Component\Translation\Translator;
   use Symfony\Component\Translation\Formatter\MessageFormatter;

   $translator = new Translator('fr_FR', new MessageFormatter());
   ```

TwigBridge
----------

 * deprecated the `Symfony\Bridge\Twig\Form\TwigRenderer` class, use the `FormRenderer`
   class from the Form component instead
   
    * the service `twig.form.renderer` is now an instance of `FormRenderer`. 
      So you might have to adjust your type-hints to `FormRendererInterface` if you are still relying on 
      the `TwigRendererInterface` which was deprecated in Symfony 3.2
      
    * retrieving the Renderer runtime from the twig environment via 
      `$twig->getRuntime('Symfony\Bridge\Twig\Form\TwigRenderer')` is not working anymore 
       and should be replaced with `$twig->getRuntime('Symfony\Component\Form\FormRenderer')` instead

 * deprecated `Symfony\Bridge\Twig\Command\DebugCommand::set/getTwigEnvironment` and the ability
   to pass a command name as first argument

 * deprecated `Symfony\Bridge\Twig\Command\LintCommand::set/getTwigEnvironment` and the ability
   to pass a command name as first argument

TwigBundle
----------

 * deprecated the `Symfony\Bundle\TwigBundle\Command\DebugCommand` class, use the `DebugCommand`
   class from the Twig bridge instead

 * deprecated relying on the `ContainerAwareInterface` implementation for
   `Symfony\Bundle\TwigBundle\Command\LintCommand`

Validator
---------

 * Not setting the `strict` option of the `Choice` constraint to `true` is
   deprecated and will throw an exception in Symfony 4.0.

Yaml
----

 * the `Dumper`, `Parser`, and `Yaml` classes are marked as final

 * using the `!php/object:` tag is deprecated and won't be supported in 4.0. Use
   the `!php/object` tag (without the colon) instead.

 * using the `!php/const:` tag is deprecated and won't be supported in 4.0. Use
   the `!php/const` tag (without the colon) instead.

   Before:

   ```yml
   !php/const:PHP_INT_MAX
   ```

   After:

   ```yml
   !php/const PHP_INT_MAX
   ```

 * Support for the `!str` tag is deprecated, use the `!!str` tag instead.

 * Using the non-specific tag `!` is deprecated and will have a different
   behavior in 4.0. Use a plain integer or `!!float` instead.

 * Using the `Yaml::PARSE_KEYS_AS_STRINGS` flag is deprecated as it will be
   removed in 4.0.

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
