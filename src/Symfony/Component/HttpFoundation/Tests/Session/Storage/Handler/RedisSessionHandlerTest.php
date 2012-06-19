<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

/**
 * RedisSessionHandlerTest
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class RedisSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RedisSessionHandler
     */
    private $handler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $redis;

    public static function setUpBeforeClass()
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('The "redis" extension must be loaded');
        }
    }


    protected function setup()
    {
        $this->redis = $this->getMockBuilder('Redis')->disableOriginalConstructor()->getMock();
        $this->handler = new RedisSessionHandler($this->redis, 86400);
    }

    public function testOpen()
    {
        $this->assertTrue($this->handler->open('test', 'test'));
    }

    public function testClose()
    {
        $this->assertTrue($this->handler->close());
    }

    public function testGc()
    {
        $this->assertTrue($this->handler->gc(200));
    }

    public function testWrite()
    {
        $data = array();

        $this->redis->expects($this->once())
             ->method('setex')
             ->will($this->returnCallback(function($key, $lifetime, $value) use (&$data) {
                 $data[$key] = $value;
                 return true;
             }));

        $this->handler->write('sessionid', 'foobar');

        $this->assertArrayHasKey('sessionid', $data);
        $this->assertEquals($data['sessionid'], 'foobar');
    }

    public function testRead()
    {
        $data = array(
            'foo' => 'bar',
        );

        $this->redis->expects($this->any())
             ->method('get')
             ->will($this->returnCallback(function($key) use (&$data) {
                 return isset($data[$key]) ? $data[$key] : false;
             }));

        $this->assertEquals('bar', $this->handler->read('foo'));
        $this->assertEquals('', $this->handler->read('not-exist'));
    }

    public function testDestroy()
    {
        $data = array(
            'foo' => 'bar'
        );

        $this->redis->expects($this->any())
             ->method('delete')
             ->will($this->returnCallback(function($key) use (&$data) {
                 if (!isset($data[$key])) {
                     return false;
                 }
                 unset($data[$key]);
                 return 1;
             }));

        $this->assertTrue($this->handler->destroy('foo'));
        $this->assertArrayNotHasKey('foo', $data);

        $this->assertFalse($this->handler->destroy('not-exist'));
    }
}
