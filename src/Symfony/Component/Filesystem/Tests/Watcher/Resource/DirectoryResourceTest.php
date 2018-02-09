<?php

/*
 * This file is part of the symfony project.
 *
 * @author     pierre
 * @copyright  Copyright (c) 2018
 */

namespace Symfony\Component\Filesystem\Tests\Watcher\Resource;

use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\Filesystem\Watcher\FileChangeEvent;
use Symfony\Component\Filesystem\Watcher\Resource\DirectoryResource;

class DirectoryResourceTest extends FilesystemTestCase
{
    public function testCreateFile()
    {
        $dir = $this->workspace.'/foo';
        mkdir($dir);

        $resource = new DirectoryResource($dir);

        $this->assertSame(array(), $resource->detectChanges());

        touch($dir.'/foo.txt');

        $this->assertEquals(array(new FileChangeEvent($dir.'/foo.txt', FileChangeEvent::FILE_CREATED)), $resource->detectChanges());
        $this->assertSame(array(), $resource->detectChanges());
    }

    public function testDeleteFile()
    {
        $dir = $this->workspace.'/foo';
        mkdir($dir);

        touch($dir.'/foo.txt');
        touch($dir.'/bar.txt');

        $resource = new DirectoryResource($dir);

        $this->assertSame(array(), $resource->detectChanges());

        unlink($dir.'/foo.txt');

        $this->assertEquals(array(new FileChangeEvent($dir.'/foo.txt', FileChangeEvent::FILE_DELETED)), $resource->detectChanges());
        $this->assertSame(array(), $resource->detectChanges());
    }

    public function testFileChanges()
    {
        $dir = $this->workspace.'/foo';
        mkdir($dir);

        touch($dir.'/foo.txt');
        touch($dir.'/bar.txt');

        $resource = new DirectoryResource($dir);

        $this->assertSame(array(), $resource->detectChanges());

        touch($dir.'/foo.txt', time() + 1);

        $this->assertEquals(array(new FileChangeEvent($dir.'/foo.txt', FileChangeEvent::FILE_CHANGED)), $resource->detectChanges());
        $this->assertSame(array(), $resource->detectChanges());
    }
}
