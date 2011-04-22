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
    }

    public function testMultipleInstances()
    {
        $storage = new FilesystemSessionStorage($this->path);
        $storage->start();
        $storage->write('foo', 'bar');

        $storage = new FilesystemSessionStorage($this->path);
        $storage->start();
        $this->assertEquals('bar', $storage->read('foo'), 'values persist between instances');
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
}
