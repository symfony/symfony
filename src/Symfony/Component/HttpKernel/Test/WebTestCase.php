<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Test;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Client;

/**
 * WebTestCase is the base class for functional tests.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class WebTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Creates a Client.
     *
     * @param array   $options An array of options to pass to the createKernel class
     * @param Boolean $debug   The debug flag
     * @param array   $server  An array of server parameters
     *
     * @return Client A Client instance
     */
    abstract public function createClient(array $options = array(), array $server = array());
}
