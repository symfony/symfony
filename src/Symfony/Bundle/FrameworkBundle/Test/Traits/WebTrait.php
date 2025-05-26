<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * WebTestCase is the base class for functional tests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait WebTrait
{
    protected static ?AbstractBrowser $client = null;

    protected function tearDown(): void
    {
        parent::tearDown();
        self::$client = null;
    }

    /**
     * @param array $options An array of options to pass to the createKernel method
     * @param array $server  An array of server parameters
     */
    public static function createClient(array $options = [], array $server = []): ?AbstractBrowser
    {
        if (static::$booted) {
            throw new \LogicException(\sprintf('Booting the kernel before calling "%s()" is not supported, the kernel should only be booted once.', __METHOD__));
        }

        $kernel = static::bootKernel($options);

        try {
            self::$client = $kernel->getContainer()->get('test.client');
        } catch (ServiceNotFoundException) {
            if (class_exists(KernelBrowser::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
            }
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit".');
        }

        self::$client->setServerParameters($server);

        return self::$client;
    }

    public static function getClient(?AbstractBrowser $newClient = null): ?AbstractBrowser
    {
        if (0 < \func_num_args()) {
            self::$client = $newClient;
        }

        if (!self::$client instanceof AbstractBrowser) {
            static::fail(\sprintf('A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?', __CLASS__));
        }

        return self::$client;
    }
}
