<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Amqp\UrlParser;

class UrlParserTest extends TestCase
{
    public function provideUri()
    {
        yield array('', array(
            'host' => 'localhost',
            'login' => 'guest',
            'password' => 'guest',
            'port' => 5672,
            'vhost' => '/',
        ));
        yield array('amqp://localhost', array(
            'host' => 'localhost',
            'login' => 'guest',
            'password' => 'guest',
            'port' => 5672,
            'vhost' => '/',
        ));
        yield array('amqp://localhost/', array(
            'host' => 'localhost',
            'login' => 'guest',
            'password' => 'guest',
            'port' => 5672,
            'vhost' => '/',
        ));
        yield array('amqp://localhost//', array(
            'host' => 'localhost',
            'login' => 'guest',
            'password' => 'guest',
            'port' => 5672,
            'vhost' => '/',
        ));
        yield array('amqp://foo:bar@rabbitmq-3.lxc:1234/symfony_amqp', array(
            'host' => 'rabbitmq-3.lxc',
            'login' => 'foo',
            'password' => 'bar',
            'port' => 1234,
            'vhost' => 'symfony_amqp',
        ));
    }

    /**
     * @dataProvider provideUri
     * */
    public function testParse($url, $expected)
    {
        $parts = UrlParser::parseUrl($url);

        $this->assertEquals($expected, $parts);
    }
}
