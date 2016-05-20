<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing;

use Symfony\Component\Routing\Router;

class RouterTest extends \PHPUnit_Framework_TestCase
{
    public function testIsValidHost()
    {
        $testData = array(
            // $expected, $host, $trustedDomains, $description
            array(true, 'example.com', array('example.com'), 'Naked domain'),
            array(true, 'stof.example.com', array('stof.example.com'), 'Fully qualified domain name'),
            array(true, 'stof.example.com', array('example.com'), 'Valid subdomain'),
            array(false, 'example.net', array('example.com'), 'Invalid domain'),
            array(false, '.example.com', array('stof.example.com'), 'Invalid subdomain'),
            array(false, 'example-com', array('example.com'), 'Regex should match . literally'),
            array(false, 'www.attacker.com?example.com', array('example.com'), 'Spoofed host'),
            array(false, 'example.com.attacker.com', array('example.com'), 'Spoofed subdomain'),
            array(true, 'example.com.', array('example.com'), 'Trailing . on host is actually valid'),
            array(true, 'www-dev.example.com', array('example.com'), 'host with dashes is valid'),
            array(true, 'www.example.com:8080', array('example.com'), 'host:port is valid'),
        );
 
        $router = new Router($this->getMock('Symfony\Component\Config\Loader\LoaderInterface'), null);
 
        foreach ($testData as $test) {
            list($expected, $host, $trustedDomains, $description) = $test;
 
            $this->assertEquals($expected, $router->isValidHost($host, $trustedDomains), $description);
        }
    }
}
