<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests\Watcher\Resource;

use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\Filesystem\Watcher\FileChangeEvent;
use Symfony\Component\Filesystem\Watcher\Resource\DirectoryResource;

class DirectoryResourceTest extends FilesystemTestCase
{
    public function testCreateFile()
    {
        $dir = $this->workspace.\DIRECTORY_SEPARATOR.'foo';
        mkdir($dir);

        $resource = new DirectoryResource($dir);

        $this->assertSame([], $resource->detectChanges());

        touch($dir.'/foo.txt');

        $this->assertEquals([new FileChangeEvent($dir.\DIRECTORY_SEPARATOR.'foo.txt', FileChangeEvent::FILE_CREATED)], $resource->detectChanges());
        $this->assertSame([], $resource->detectChanges());
    }

    public function testDeleteFile()
    {
        $dir = $this->workspace.\DIRECTORY_SEPARATOR.'foo';
        mkdir($dir);

        touch($dir.'/foo.txt');
        touch($dir.'/bar.txt');

        $resource = new DirectoryResource($dir);

        $this->assertSame([], $resource->detectChanges());

        unlink($dir.'/foo.txt');

        $this->assertEquals([new FileChangeEvent($dir.\DIRECTORY_SEPARATOR.'foo.txt', FileChangeEvent::FILE_DELETED)], $resource->detectChanges());
        $this->assertSame([], $resource->detectChanges());
    }

    public function testFileChanges()
    {
        $dir = $this->workspace.\DIRECTORY_SEPARATOR.'foo';
        mkdir($dir);

        touch($dir.'/foo.txt');
        touch($dir.'/bar.txt');

        $resource = new DirectoryResource($dir);

        $this->assertSame([], $resource->detectChanges());

        touch($dir.'/foo.txt', time() + 1);

        $this->assertEquals([new FileChangeEvent($dir.\DIRECTORY_SEPARATOR.'foo.txt', FileChangeEvent::FILE_CHANGED)], $resource->detectChanges());
        $this->assertSame([], $resource->detectChanges());
    }
}
