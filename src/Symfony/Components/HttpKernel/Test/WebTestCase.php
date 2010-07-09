<?php

namespace Symfony\Components\HttpKernel\Test;

use Symfony\Components\DomCrawler\Crawler;
use Symfony\Components\HttpKernel\Client;

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
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
     * @return Symfony\Framework\Client A Client instance
     */
    abstract public function createClient(array $options = array(), array $server = array());
}
