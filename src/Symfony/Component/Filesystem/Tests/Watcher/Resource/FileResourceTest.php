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
use Symfony\Component\Filesystem\Watcher\Resource\FileResource;

class FileResourceTest extends FilesystemTestCase
{
    public function testFileChanges()
    {
        $file = $this->workspace.'/foo.txt';
        touch($file);

        $resource = new FileResource($file);

        $this->assertSame([], $resource->detectChanges());

        touch($file, time() + 1);

        $this->assertEquals([new FileChangeEvent($file, FileChangeEvent::FILE_CHANGED)], $resource->detectChanges());
        $this->assertSame([], $resource->detectChanges());
    }
}
