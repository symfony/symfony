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
     * The method can't be declared abstract and static at the same time because
     * it produces a Strict Standards notice since PHP 5.3 and Late Static Binding
     * implementation. That's why, it throws a \LogicException and must be
     * overriden by a more specific class.
     *
     * @param array $options An array of options to pass to the createKernel class
     * @param array $server  An array of server parameters
     *
     * @return Client A Client instance
     *
     * @throws \LogicException
     */
    static protected function createClient(array $options = array(), array $server = array())
    {
        throw new \LogicException('WebTestCase::createClient() must be overriden in a more specific class.');
    }
}
