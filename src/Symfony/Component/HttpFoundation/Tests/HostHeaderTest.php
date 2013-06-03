<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\HttpFoundation\HostHeader;

class HostHeaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideFromValidStringData
     */
    public function testFromValidString($string, array $hostAndPort)
    {
        $header = HostHeader::fromString($string);
        list($host, $port) = $hostAndPort;
        $this->assertEquals($host, $header->getHost());
        $this->assertEquals($port, $header->getPort());
    }

    public function provideFromValidStringData()
    {
        return array(
            array('example.org', array('example.org', null)),
            array('example.org:90', array('example.org', 90)),
            array('sub.example.org', array('sub.example.org', null)),
            array('sub.example.org:8080', array('sub.example.org', 8080)),
            array('192.168.1.1', array('192.168.1.1', null)),
            array('127.0.0.1:90', array('127.0.0.1', 90)),
            array('[::1]:90', array('[::1]', 90)),
            array('[2607:f0d0:1002:0051:0000:0000:0000:0004]:90', array('[2607:f0d0:1002:0051:0000:0000:0000:0004]', 90)),
            array('', array('', null)),
            array(':90', array('', 90)),
        );
    }

    /**
     * @dataProvider invalidHostProvider
     */
    public function testFromInvalidString($string)
    {
        $this->setExpectedException('\UnexpectedValueException');
        $header = HostHeader::fromString($string);
    }

    public function invalidHostProvider()
    {
        return array(
            array('example.org:blah'),
            array('whitespace not valid'),
            array('asdf:asdf:90'),
            array('1.1.1.1.1   '),
            array('<script>alert(1);</script>'),
            array("asdf\nHost: example.org"),
            array('   example.org:90'),
        );
    }
}
