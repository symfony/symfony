<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\ServerBag;

/**
 * ServerBagTest
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ServerBagTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldExtractHeadersFromServerArray()
    {
        $server = array(
            'SOME_SERVER_VARIABLE' => 'value',
            'SOME_SERVER_VARIABLE2' => 'value',
            'ROOT' => 'value',
            'HTTP_CONTENT_TYPE' => 'text/html',
            'HTTP_CONTENT_LENGTH' => '0',
            'HTTP_ETAG' => 'asdf',
            'PHP_AUTH_USER' => 'foo',
            'PHP_AUTH_PW' => 'bar',
        );

        $bag = new ServerBag($server);

        $this->assertEquals(array(
            'CONTENT_TYPE' => 'text/html',
            'CONTENT_LENGTH' => '0',
            'ETAG' => 'asdf',
            'AUTHORIZATION' => 'Basic '.base64_encode('foo:bar'),
        ), $bag->getHeaders());
    }

    public function testHttpPasswordIsOptional()
    {
        $bag = new ServerBag(array('PHP_AUTH_USER' => 'foo'));

        $this->assertEquals(array('AUTHORIZATION' => 'Basic '.base64_encode('foo:')), $bag->getHeaders());
    }
}
