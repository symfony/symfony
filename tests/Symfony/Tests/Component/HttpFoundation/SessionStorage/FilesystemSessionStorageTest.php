<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\FilesystemSessionStorage;

class FilesystemSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    private $path;

    protected function setUp()
    {
        $this->path = sys_get_temp_dir().'/sf2/session_test';
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    protected function tearDown()
    {
        array_map('unlink', glob($this->path.'/*.session'));
        rmdir($this->path);

        $this->path = null;
    }

    public function testMultipleInstances()
    {
        $storage1 = new FilesystemSessionStorage($this->path);
        $storage1->start();
        $storage1->write('foo', 'bar');

        $storage2 = new FilesystemSessionStorage($this->path);
        $storage2->start();
        $this->assertEquals('bar', $storage2->read('foo'), 'values persist between instances');
    }

    public function testGetIdThrowsErrorBeforeStart()
    {
        $this->setExpectedException('RuntimeException');

        $storage = new FilesystemSessionStorage($this->path);
        $storage->getId();
    }

    public function testGetIdWorksAfterStart()
    {
        $storage = new FilesystemSessionStorage($this->path);
        $storage->start();
        $storage->getId();
    }

    public function testGetIdSetByOptions()
    {
        $previous = ini_get('session.use_cookies');

        ini_set('session.use_cookies', false);

        $storage = new FilesystemSessionStorage($this->path, array('id' => 'symfony2-sessionId'));
        $storage->start();

        $this->assertEquals('symfony2-sessionId', $storage->getId());

        ini_set('session.use_cookies', $previous);
    }

    public function testRemoveVariable()
    {
        $storage = new FilesystemSessionStorage($this->path);
        $storage->start();

        $storage->write('foo', 'bar');

        $this->assertEquals('bar', $storage->read('foo'));

        $storage->remove('foo', 'bar');

        $this->assertNull($storage->read('foo'));
    }

    public function testRegenerate()
    {
        $storage = new FilesystemSessionStorage($this->path);
        $storage->start();
        $storage->write('foo', 'bar');

        $storage->regenerate();

        $this->assertEquals('bar', $storage->read('foo'));

        $storage->regenerate(true);

        $this->assertNull($storage->read('foo'));
    }
}
