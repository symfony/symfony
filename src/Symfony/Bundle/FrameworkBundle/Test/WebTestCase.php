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
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * WebTestCase is the base class for functional tests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class WebTestCase extends KernelTestCase
{
    /**
     * Creates a Client.
     *
     * @param array $options An array of options to pass to the createKernel method
     * @param array $server  An array of server parameters
     *
     * @return Client A Client instance
     */
    protected static function createClient(array $options = [], array $server = [])
    {
        $kernel = static::bootKernel($options);

        try {
            $client = $kernel->getContainer()->get('test.client');
        } catch (ServiceNotFoundException $e) {
            if (class_exists(Client::class)) {
                throw new \LogicException('You cannot create the client used in functional tests if the "framework.test" config is not set to true.');
            }
            throw new \LogicException('You cannot create the client used in functional tests if the BrowserKit component is not available. Try running "composer require symfony/browser-kit"');
        }

        $client->setServerParameters($server);

        return $client;
    }
}
