<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests\Watcher;

use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\Filesystem\Tests\Fixtures\ChangeFileResource;
use Symfony\Component\Filesystem\Watcher\FileChangeEvent;
use Symfony\Component\Filesystem\Watcher\FileChangeWatcher;
use Symfony\Component\Filesystem\Watcher\Resource\Locator\LocatorInterface;
use Symfony\Component\Filesystem\Watcher\Resource\ResourceInterface;

class FileSystemWatchTest extends FilesystemTestCase
{
    public function testWatch()
    {
        $workspace = $this->workspace;

        $locator = new class($workspace) implements LocatorInterface {
            private $workspace;

            public function __construct($workspace)
            {
                $this->workspace = $workspace;
            }

            public function locate($path): ?ResourceInterface
            {
                return new ChangeFileResource($this->workspace.'/foobar.txt');
            }
        };

        $watcher = new FileChangeWatcher($locator, 2);

        $count = 0;
        $watcher->watch($this->workspace, function ($file, $code) use (&$count) {
            $this->assertSame($this->workspace.'/foobar.txt', $file);
            $this->assertSame(FileChangeEvent::FILE_CHANGED, $code);
            ++$count;
        });

        $this->assertSame(2, $count);
    }
}
