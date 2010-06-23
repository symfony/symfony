<?php

namespace Symfony\Foundation\Test;

use Symfony\Components\HttpKernel\Test\WebTestCase as BaseWebTestCase;

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
abstract class WebTestCase extends BaseWebTestCase
{
    protected $kernel;

    /**
     * Creates a Client.
     *
     * @param array   $options An array of options to pass to the createKernel class
     * @param array   $server  An array of server parameters
     *
     * @return Symfony\Foundation\Client A Client instance
     */
    public function createClient(array $options = array(), array $server = array())
    {
        $this->kernel = $this->createKernel($options);
        $this->kernel->boot();

        $client = $this->kernel->getContainer()->getTest_ClientService();
        $client->setServerParameters($server);

        return $client;
    }

    /**
     * Creates a Kernel.
     *
     * @param array $options An array of options
     *
     * @return Symfony\Components\HttpKernel\HttpKernelInterface A HttpKernelInterface instance
     */
    abstract protected function createKernel(array $options = array());
}
