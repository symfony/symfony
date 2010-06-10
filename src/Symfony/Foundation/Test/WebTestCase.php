<?php

namespace Symfony\Foundation\Test;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * WebTestCase is the base class for functional tests.
 *
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class WebTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Creates a Client.
     *
     * @param string  $environment The environment
     * @param Boolean $debug       The debug flag
     * @param array   $server      An array of server parameters
     *
     * @return Symfony\Foundation\Test\Client A Client instance
     */
    public function createClient($environment = 'test', $debug = true, array $server = array())
    {
        $kernel = $this->createKernel($environment, $debug);
        $kernel->boot();

        $client = $kernel->getContainer()->getTest_ClientService();
        $client->setServerParameters($server);
        $client->setTestCase($this);

        return $client;
    }

    /**
     * Creates a Kernel.
     *
     * @param string  $environment The environment
     * @param Boolean $debug       The debug flag
     *
     * @return Symfony\Components\HttpKernel\HttpKernelInterface A HttpKernelInterface instance
     */
    abstract protected function createKernel($environment, $debug);
}
