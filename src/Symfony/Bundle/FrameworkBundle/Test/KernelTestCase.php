<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * KernelTestCase is the base class for tests needing a Kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class KernelTestCase extends TestCase
{
    use KernelShutdownOnTearDownTrait;

    protected static $class;

    /**
     * @var KernelInterface
     */
    protected static $kernel;

    /**
     * Finds the directory where the phpunit.xml(.dist) is stored.
     *
     * If you run tests with the PHPUnit CLI tool, everything will work as expected.
     * If not, override this method in your test classes.
     *
     * @return string The directory where phpunit.xml(.dist) is stored
     *
     * @throws \RuntimeException
     *
     * @deprecated since 3.4 and will be removed in 4.0.
     */
    protected static function getPhpUnitXmlDir()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        if (!isset($_SERVER['argv']) || false === strpos($_SERVER['argv'][0], 'phpunit')) {
            throw new \RuntimeException('You must override the KernelTestCase::createKernel() method.');
        }

        $dir = static::getPhpUnitCliConfigArgument();
        if (null === $dir &&
            (is_file(getcwd().\DIRECTORY_SEPARATOR.'phpunit.xml') ||
            is_file(getcwd().\DIRECTORY_SEPARATOR.'phpunit.xml.dist'))) {
            $dir = getcwd();
        }

        // Can't continue
        if (null === $dir) {
            throw new \RuntimeException('Unable to guess the Kernel directory.');
        }

        if (!is_dir($dir)) {
            $dir = \dirname($dir);
        }

        return $dir;
    }

    /**
     * Finds the value of the CLI configuration option.
     *
     * PHPUnit will use the last configuration argument on the command line, so this only returns
     * the last configuration argument.
     *
     * @return string The value of the PHPUnit CLI configuration option
     *
     * @deprecated since 3.4 and will be removed in 4.0.
     */
    private static function getPhpUnitCliConfigArgument()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        $dir = null;
        $reversedArgs = array_reverse($_SERVER['argv']);
        foreach ($reversedArgs as $argIndex => $testArg) {
            if (preg_match('/^-[^ \-]*c$/', $testArg) || '--configuration' === $testArg) {
                $dir = realpath($reversedArgs[$argIndex - 1]);
                break;
            } elseif (0 === strpos($testArg, '--configuration=')) {
                $argPath = substr($testArg, \strlen('--configuration='));
                $dir = realpath($argPath);
                break;
            } elseif (0 === strpos($testArg, '-c')) {
                $argPath = substr($testArg, \strlen('-c'));
                $dir = realpath($argPath);
                break;
            }
        }

        return $dir;
    }

    /**
     * Attempts to guess the kernel location.
     *
     * When the Kernel is located, the file is required.
     *
     * @return string The Kernel class name
     *
     * @throws \RuntimeException
     */
    protected static function getKernelClass()
    {
        if (isset($_SERVER['KERNEL_CLASS']) || isset($_ENV['KERNEL_CLASS'])) {
            $class = isset($_ENV['KERNEL_CLASS']) ? $_ENV['KERNEL_CLASS'] : $_SERVER['KERNEL_CLASS'];
            if (!class_exists($class)) {
                throw new \RuntimeException(sprintf('Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the %s::createKernel() method.', $class, static::class));
            }

            return $class;
        } else {
            @trigger_error(sprintf('Using the KERNEL_DIR environment variable or the automatic guessing based on the phpunit.xml / phpunit.xml.dist file location is deprecated since Symfony 3.4. Set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel instead. Not setting the KERNEL_CLASS environment variable will throw an exception on 4.0 unless you override the %1$::createKernel() or %1$::getKernelClass() method.', static::class), E_USER_DEPRECATED);
        }

        if (isset($_SERVER['KERNEL_DIR']) || isset($_ENV['KERNEL_DIR'])) {
            $dir = isset($_ENV['KERNEL_DIR']) ? $_ENV['KERNEL_DIR'] : $_SERVER['KERNEL_DIR'];

            if (!is_dir($dir)) {
                $phpUnitDir = static::getPhpUnitXmlDir();
                if (is_dir("$phpUnitDir/$dir")) {
                    $dir = "$phpUnitDir/$dir";
                }
            }
        } else {
            $dir = static::getPhpUnitXmlDir();
        }

        $finder = new Finder();
        $finder->name('*Kernel.php')->depth(0)->in($dir);
        $results = iterator_to_array($finder);
        if (!\count($results)) {
            throw new \RuntimeException('Either set KERNEL_DIR in your phpunit.xml according to https://symfony.com/doc/current/book/testing.html#your-first-functional-test or override the WebTestCase::createKernel() method.');
        }

        $file = current($results);
        $class = $file->getBasename('.php');

        require_once $file;

        return $class;
    }

    /**
     * Boots the Kernel for this test.
     *
     * @return KernelInterface A KernelInterface instance
     */
    protected static function bootKernel(array $options = [])
    {
        static::ensureKernelShutdown();

        static::$kernel = static::createKernel($options);
        static::$kernel->boot();

        return static::$kernel;
    }

    /**
     * Creates a Kernel.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     *
     * @return KernelInterface A KernelInterface instance
     */
    protected static function createKernel(array $options = [])
    {
        if (null === static::$class) {
            static::$class = static::getKernelClass();
        }

        if (isset($options['environment'])) {
            $env = $options['environment'];
        } elseif (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } else {
            $env = 'test';
        }

        if (isset($options['debug'])) {
            $debug = $options['debug'];
        } elseif (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = $_SERVER['APP_DEBUG'];
        } else {
            $debug = true;
        }

        return new static::$class($env, $debug);
    }

    /**
     * Shuts the kernel down if it was used in the test - called by the tearDown method by default.
     */
    protected static function ensureKernelShutdown()
    {
        if (null !== static::$kernel) {
            $container = static::$kernel->getContainer();
            static::$kernel->shutdown();
            if ($container instanceof ResettableContainerInterface) {
                $container->reset();
            }
        }
    }
}
