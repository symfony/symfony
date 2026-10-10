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

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * WebTestCase is the base class for functional tests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class WebTestCase extends KernelTestCase
{
    use WebTestAssertionsTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        self::getClient(null);
    }

    /**
     * Creates a KernelBrowser.
     *
     * Calling this method more than once in the same test will not reboot the kernel; instead,
     * an additional KernelBrowser bound to the already-booted kernel is returned. This allows
     * creating several independent clients (for example to simulate multiple users), as documented
     * in "Testing with Several Clients" without tearing down a client created earlier.
     *
     * @param array $options An array of options to pass to the createKernel method; ignored if the
     *                        kernel has already been booted by a previous createClient() call
     * @param array $server  An array of server parameters
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $kernel = static::$booted ? static::$kernel : static::bootKernel($options);

        try {
            $client = $kernel->getContainer()->get('test.client');
        } catch (ServiceNotFoundException) {
            if (class_exists(KernelBrowser::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
            }
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit".');
        }

        $client->setServerParameters($server);

        return self::getClient($client);
    }
}
