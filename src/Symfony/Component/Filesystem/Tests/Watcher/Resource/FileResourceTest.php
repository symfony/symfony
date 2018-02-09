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
use Symfony\Component\Filesystem\Watcher\Resource\FileResource;

class FileResourceTest extends FilesystemTestCase
{
    public function testFileChanges()
    {
        $file = $this->workspace.'/foo.txt';
        touch($file);

        $resource = new FileResource($file);

        $this->assertSame(array(), $resource->detectChanges());

        touch($file, time() + 1);

        $this->assertEquals(array(new FileChangeEvent($file, FileChangeEvent::FILE_CHANGED)), $resource->detectChanges());
        $this->assertSame(array(), $resource->detectChanges());
    }
}
