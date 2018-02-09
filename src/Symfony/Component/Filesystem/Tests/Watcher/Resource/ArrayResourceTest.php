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
use Symfony\Component\Filesystem\Watcher\Resource\ArrayResource;
use Symfony\Component\Filesystem\Watcher\Resource\FileResource;

class ArrayResourceTest extends FilesystemTestCase
{
    public function testFileChange()
    {
        $file = $this->workspace.'/foo.txt';
        touch($file);

        $resource = new ArrayResource(array(new FileResource($file)));

        $this->assertSame(array(), $resource->detectChanges());

        touch($file, time() + 1);

        $this->assertEquals(array(new FileChangeEvent($file, FileChangeEvent::FILE_CHANGED)), $resource->detectChanges());
        $this->assertSame(array(), $resource->detectChanges());
    }
}
