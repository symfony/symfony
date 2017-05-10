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

use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * WebTestHelper is an alternative to WebTestCase when you cannot inherit from
 * WebTestCase.
 *
 * @author Guillaume Royer <guilro@redado.org>
 */
class WebTestHelper extends WebTestCase
{
    protected static $class;

    /**
     * @var KernelInterface
     */
    protected static $kernel;

    /**
     * Creates a Client.
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server  An array of server parameters
     *
     * @return Client A Client instance
     */
    public static function createClient(array $options = array(), array $server = array())
    {
        return parent::createClient($options, $server);
    }

    /**
     * Finds the directory where the phpunit.xml(.dist) is stored.
     *
     * If you run tests with the PHPUnit CLI tool, everything will work as expected.
     * If not, override this method in your test classes.
     *
     * @return string The directory where phpunit.xml(.dist) is stored
     *
     * @throws \RuntimeException
     */
    public static function getPhpUnitXmlDir()
    {
        try {
            return parent::getPhpUnitXmlDir();
        } catch (\RuntimeException $exception) {
            $message = str_replace('WebTestCase', 'WebTestHelper', $exception->getMessage());
            throw new \RuntimeException($message);
        }
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
    public static function getKernelClass()
    {
        try {
            return parent::getKernelClass();
        } catch (\RuntimeException $exception) {
            $message = str_replace('WebTestCase', 'WebTestHelper', $exception->getMessage());
            throw new \RuntimeException($message);
        }
    }

    /**
     * Creates a Kernel.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     *
     * @param array $options An array of options
     *
     * @return KernelInterface A KernelInterface instance
     */
    public static function createKernel(array $options = array())
    {
        return parent::createKernel($options);
    }

    /**
     * Shuts the kernel down. Should be used in tearDown with PHPUnit
     */
    public static function shutdownKernel()
    {
        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }
    }
}
