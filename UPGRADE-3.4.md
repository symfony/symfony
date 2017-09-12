UPGRADE FROM 3.3 to 3.4
=======================

DependencyInjection
-------------------

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

 * Top-level anonymous services in XML are deprecated and will throw an exception in Symfony 4.0.

 * Case insensitivity of parameter names is deprecated and will be removed in 4.0.

 * The `ResolveDefinitionTemplatesPass` class is deprecated and will be removed in 4.0.
   Use the `ResolveChildDefinitionsPass` class instead.

Debug
-----

 * Support for stacked errors in the `ErrorHandler` is deprecated and will be removed in Symfony 4.0.

Filesystem
----------

 * The `Symfony\Component\Filesystem\LockHandler` class has been deprecated,
   use the `Symfony\Component\Lock\Store\FlockStore` class
   or the `Symfony\Component\Lock\Store\FlockStore\SemaphoreStore` class directly instead.

Finder
------

 * The `Symfony\Component\Finder\Iterator\FilterIterator` class has been
   deprecated and will be removed in 4.0 as it used to fix a bug which existed
   before version 5.5.23/5.6.7.

FrameworkBundle
---------------

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
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\HttpKernel\DependencyInjection\AddCacheClearerPass` class instead.

 * The `Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddCacheWarmerPass`
   class has been deprecated and will be removed in 4.0. Use the
   `Symfony\Component\HttpKernel\DependencyInjection\AddCacheWarmerPass` class instead.

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
   `Symfony\Component\Routing\RouterInteface` as
    first argument. Not passing it is deprecated and will throw
    a `TypeError` in 4.0.

 * `RouterMatchCommand::__construct()` now takes an instance of
   `Symfony\Component\Routing\RouterInteface` as
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

HttpKernel
----------

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
       AppBundle\Command:
           resource: '../../src/AppBundle/Command/*'
           tags: ['console.command']
   ```

 * The `getCacheDir()` method of your kernel should not be called while building the container.
   Use the `%kernel.cache_dir%` parameter instead. Not doing so may break the `cache:clear` command.

 * The `Symfony\Component\HttpKernel\Config\EnvParametersResource` class has been deprecated and will be removed in 4.0.

Process
-------

 * The `Symfony\Component\Process\ProcessBuilder` class has been deprecated,
   use the `Symfony\Component\Process\Process` class directly instead.

Profiler
--------

 * The `profiler.matcher` option has been deprecated.

SecurityBundle
--------------

 * Using voters that do not implement the `VoterInterface`is now deprecated in
   the `AccessDecisionManager` and this functionality will be removed in 4.0.

 * `FirewallContext::getListeners()` now returns `\Traversable|array`

 * `InitAclCommand::__construct()` now takes an instance of
   `Doctrine\DBAL\Connection`  as first argument. Not passing it is
    deprecated and will throw a `TypeError` in 4.0.

 * `SetAclCommand::__construct()` now takes an instance of
   `Symfony\Component\Security\Acl\Model\MutableAclProviderInterfaceConnection`
    as first argument. Not passing it is deprecated and will throw a `TypeError`
    in 4.0.

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
