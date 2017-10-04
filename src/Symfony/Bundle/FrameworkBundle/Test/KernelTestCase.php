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
use Symfony\Component\ClassLoader\ClassFinder;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * KernelTestCase is the base class for tests needing a Kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class KernelTestCase extends TestCase
{
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
        @trigger_error(sprintf('The %s() method is deprecated since 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        if (!isset($_SERVER['argv']) || false === strpos($_SERVER['argv'][0], 'phpunit')) {
            throw new \RuntimeException('You must override the KernelTestCase::createKernel() method.');
        }

        $dir = static::getPhpUnitCliConfigArgument();
        if (null === $dir &&
            (is_file(getcwd().DIRECTORY_SEPARATOR.'phpunit.xml') ||
            is_file(getcwd().DIRECTORY_SEPARATOR.'phpunit.xml.dist'))) {
            $dir = getcwd();
        }

        // Can't continue
        if (null === $dir) {
            throw new \RuntimeException('Unable to guess the Kernel directory.');
        }

        if (!is_dir($dir)) {
            $dir = dirname($dir);
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
        @trigger_error(sprintf('The %s() method is deprecated since 3.4 and will be removed in 4.0.', __METHOD__), E_USER_DEPRECATED);

        $dir = null;
        $reversedArgs = array_reverse($_SERVER['argv']);
        foreach ($reversedArgs as $argIndex => $testArg) {
            if (preg_match('/^-[^ \-]*c$/', $testArg) || '--configuration' === $testArg) {
                $dir = realpath($reversedArgs[$argIndex - 1]);
                break;
            } elseif (0 === strpos($testArg, '--configuration=')) {
                $argPath = substr($testArg, strlen('--configuration='));
                $dir = realpath($argPath);
                break;
            } elseif (0 === strpos($testArg, '-c')) {
                $argPath = substr($testArg, strlen('-c'));
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
            $kernelClass = isset($_SERVER['KERNEL_CLASS']) ? $_SERVER['KERNEL_CLASS'] : $_ENV['KERNEL_CLASS'];
            if (!class_exists($kernelClass)) {
                throw new \RuntimeException(sprintf('Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the %s::createKernel() method.', $class, static::class));
            }

            return $kernelClass;
        }

        if (isset($_SERVER['KERNEL_DIR']) || isset($_ENV['KERNEL_DIR'])) {
            @trigger_error(
                sprintf('Using the KERNEL_DIR environment variable or the automatic guessing based on the phpunit.xml / phpunit.xml.dist file location is deprecated since 3.4. '.
                    'Set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel class or override the %1$::createKernel() or %1$::getKernelClass() method, otherwise default test kernel implementation would be used.',
                    static::class),
                E_USER_DEPRECATED);
            $dir = isset($_SERVER['KERNEL_DIR']) ? $_SERVER['KERNEL_DIR'] : $_ENV['KERNEL_DIR'];

            if (!is_dir($dir)) {
                $phpUnitDir = static::getPhpUnitXmlDir();
                if (is_dir("$phpUnitDir/$dir")) {
                    $dir = "$phpUnitDir/$dir";
                }
            }

            if (!$kernelClass = static::findKernelClassInDirectory($dir)) {
                throw new \RuntimeException(sprintf('There is no test kernel class in specified KERNEL_DIR "%s"', $_SERVER['KERNEL_CLASS']));
            }

            return $kernelClass;
        }

        // try to find kernel class near phpunit.xml/phpunit.xml.dist files
        // this functionality would be deleted in 4.0
        if ($kernelClass = static::findKernelClassInDirectory(static::getPhpUnitXmlDir())) {
            return $kernelClass;
        }

        // fallback to default kernel class
        return static::getDefaultTestKernelClass();
    }

    /**
     * Tries to find kernel class in directory. Returns null if no appropriative class found.
     *
     * @param string $dir directory to be processed
     *
     * @return string|null
     */
    protected static function findKernelClassInDirectory($dir)
    {
        $kernelClass = null;

        $finder = new Finder();
        $finder->name('*Kernel.php')->depth(0)->in($dir);
        $results = iterator_to_array($finder);
        if ($results) {
            $file = current($results);

            $classes = ClassFinder::findClasses($file);
            if ($classes) {
                $kernelClass = reset($classes);
            }
        }

        return $kernelClass;
    }

    /**
     * Returns default test kernel class FQCN.
     *
     * @var string
     */
    protected static function getDefaultTestKernelClass()
    {
        return TestKernel::class;
    }

    /**
     * Boots the Kernel for this test.
     *
     * @param array $options
     *
     * @return KernelInterface A KernelInterface instance
     */
    protected static function bootKernel(array $options = array())
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
     *  * test_case
     *  * config_dir
     *  * root_config
     *  * root_dir
     *
     * @param array $options An array of options
     *
     * @return KernelInterface A KernelInterface instance
     */
    protected static function createKernel(array $options = array())
    {
        if (null === static::$class) {
            static::$class = static::getKernelClass();
        }

        if (isset($options['environment'])) {
            $env = $options['environment'];
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } elseif (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } else {
            $env = 'test';
        }

        if (isset($options['debug'])) {
            $debug = $options['debug'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = $_SERVER['APP_DEBUG'];
        } elseif (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
        } else {
            $debug = true;
        }

        $kernel = new static::$class($env, $debug);

        if ($kernel instanceof TestKernelInterface) {
            if (!isset($options['test_case'])) {
                throw new \InvalidArgumentException('The option "test_case" must be set.');
            }
            if (!isset($options['config_dir'])) {
                throw new \InvalidArgumentException('The option "config_dir" must be set.');
            }

            $kernel->setTestKernelConfiguration(
                static::getTempDir(),
                $options['test_case'],
                $options['config_dir'],
                isset($options['root_config']) ? $options['root_config'] : 'config.yml',
                isset($options['root_dir']) ? $options['root_dir'] : null);
        }

        return $kernel;
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    protected static function ensureKernelShutdown()
    {
        if (null !== static::$kernel) {
            $container = static::$kernel->getContainer();
            $kernel = static::$kernel;
            $kernel->shutdown();
            if ($container instanceof ResettableContainerInterface) {
                $container->reset();
            }
        }
    }

    /**
     * Clean up Kernel usage in this test.
     */
    protected function tearDown()
    {
        static::ensureKernelShutdown();
    }

    /**
     * Clean up before test class run.
     */
    public static function setUpBeforeClass()
    {
        static::ensureTempDirCleared();
    }

    /**
     * Clean up after test class run.
     */
    public static function tearDownAfterClass()
    {
        static::ensureTempDirCleared();
    }

    protected static function ensureTempDirCleared()
    {
        if (!file_exists(static::getTempDir())) {
            return;
        }
        $fs = new Filesystem();
        $fs->remove(static::getTempDir());
    }

    protected static function getTempDir()
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.static::getVarDir();
    }

    protected static function getVarDir()
    {
        return substr(strrchr(get_called_class(), '\\'), 1);
    }
}
