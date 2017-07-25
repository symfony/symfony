UPGRADE FROM 3.3 to 3.4
=======================

DependencyInjection
-------------------

 * Top-level anonymous services in XML are deprecated and will throw an exception in Symfony 4.0.

Debug
-----

 * Support for stacked errors in the `ErrorHandler` is deprecated and will be removed in Symfony 4.0.

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
   
 * The class `Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader` was 
   moved to the Translation component (`Symfony\Component\Translation\Loader\TranslationLoader`)

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

Process
-------

 * The `Symfony\Component\Process\ProcessBuilder` class has been deprecated,
   use the `Symfony\Component\Process\Process` class directly instead.

SecurityBundle
--------------

 * Using voters that do not implement the `VoterInterface`is now deprecated in
   the `AccessDecisionManager` and this functionality will be removed in 4.0.

 * `FirewallContext::getListeners()` now returns `\Traversable|array`

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

 * Support for the `!str` tag is deprecated, use the `!!str` tag instead.

 * Using the non-specific tag `!` is deprecated and will have a different
   behavior in 4.0. Use a plain integer or `!!float` instead.
