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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\FileSessionHandler;

/**
 * Test class for FileSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 */
class FileSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FileSessionHandler
     */
    private $handler;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $prefix = 'mocksess_';

    public function setUp()
    {
        $this->path = sys_get_temp_dir().'/filesessionhandler';
        $this->handler = new FileSessionHandler($this->path, $this->prefix);

        parent::setUp();
    }

    public function tearDown()
    {
        foreach (glob($this->path.'/*') as $file) {
            unlink($file);
        }

        rmdir($this->path);

        $this->handler = null;
    }

    public function test__construct()
    {
        $this->assertTrue(is_dir($this->path));
    }

    public function testOpen()
    {
        $this->assertTrue($this->handler->open('a', 'b'));
    }

    public function testClose()
    {
        $this->assertTrue($this->handler->close());
    }

    public function testReadWrite()
    {
        $this->assertEmpty($this->handler->read('123'));
        $this->assertTrue($this->handler->write('123', 'data'));
        $this->assertEquals('data', $this->handler->read('123'));
    }

    public function testDestroy()
    {
        $pathPrefix = $this->path.'/'.$this->prefix;
        file_put_contents($pathPrefix.'456', 'data');
        $this->handler->destroy('123');
        $this->assertEquals('data', file_get_contents($pathPrefix . '456'));
        $this->handler->destroy('456');
        $this->assertFalse(is_file($pathPrefix . '456'));
    }

    public function testGc()
    {
        $prefix = $this->path.'/'.$this->prefix;
        file_put_contents($prefix.'1', 'data');
        touch($prefix.'1', time()-86400);

        file_put_contents($prefix.'2', 'data');
        touch($prefix.'2', time()-3600);

        file_put_contents($prefix.'3', 'data');
        touch($prefix.'3', time()-300);

        file_put_contents($prefix.'4', 'data');

        $this->handler->gc(90000);
        $this->assertEquals(4, count(glob($this->path.'/*')));

        $this->handler->gc(4000);
        $this->assertEquals(3, count(glob($this->path.'/*')));

        $this->handler->gc(200);
        $this->assertEquals(1, count(glob($this->path.'/*')));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testOneSessionAtTime()
    {
        $this->handler->read(1);
        $this->handler->read(2);
    }
}
