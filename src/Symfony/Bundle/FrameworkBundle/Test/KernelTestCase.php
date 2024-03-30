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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * KernelTestCase is the base class for tests needing a Kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class KernelTestCase extends TestCase
{
    use MailerAssertionsTrait;
    use NotificationAssertionsTrait;

    protected static ?string $class = null;
    protected static ?KernelInterface $kernel = null;
    protected static bool $booted = false;

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        static::$class = null;
        static::$kernel = null;
        static::$booted = false;
    }

    /**
     * @throws \RuntimeException
     * @throws \LogicException
     */
    protected static function getKernelClass(): string
    {
        if (!isset($_SERVER['KERNEL_CLASS']) && !isset($_ENV['KERNEL_CLASS'])) {
            throw new \LogicException(sprintf('You must set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel in phpunit.xml / phpunit.xml.dist or override the "%1$s::createKernel()" or "%1$s::getKernelClass()" method.', static::class));
        }

        if (!class_exists($class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'])) {
            throw new \RuntimeException(sprintf('Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the "%s::createKernel()" method.', $class, static::class));
        }

        return $class;
    }

    /**
     * Boots the Kernel for this test.
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelShutdown();

        $kernel = static::createKernel($options);
        $kernel->boot();
        static::$kernel = $kernel;
        static::$booted = true;

        return static::$kernel;
    }

    /**
     * Provides a dedicated test container with access to both public and private
     * services. The container will not include private services that have been
     * inlined or removed. Private services will be removed when they are not
     * used by other services.
     *
     * Using this method is the best way to get a container from your test code.
     */
    protected static function getContainer(): Container
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        try {
            return self::$kernel->getContainer()->get('test.service_container');
        } catch (ServiceNotFoundException $e) {
            throw new \LogicException('Could not find service "test.service_container". Try updating the "framework.test" config to "true".', 0, $e);
        }
    }

    /**
     * Creates a Kernel.
     *
     * Available options:
     *
     *  * environment
     *  * debug
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        static::$class ??= static::getKernelClass();

        $env = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? true;

        return new static::$class($env, $debug);
    }

    /**
     * Shuts the kernel down if it was used in the test - called by the tearDown method by default.
     */
    protected static function ensureKernelShutdown()
    {
        if (null !== static::$kernel) {
            static::$kernel->boot();
            $container = static::$kernel->getContainer();
            static::$kernel->shutdown();
            static::$booted = false;

            if ($container instanceof ResetInterface) {
                $container->reset();
            }
        }
    }
}
