<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests;

/**
 * Test class for Filesystem.
 */
class FilesystemTest extends FilesystemTestCase
{
    public function testChgrpSymlinkByName()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.\DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.\DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $group = $this->getFileGroup($link);
        var_dump($group);
        $this->filesystem->chgrp($link, $group);

        $this->assertSame($group, $this->getFileGroup($link));
    }

    public function testChgrpSymlinkById()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.\DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.\DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $groupId = $this->getFileGroupId($link);
        var_dump($groupId);
        $this->filesystem->chgrp($link, $groupId);

        $this->assertSame($groupId, $this->getFileGroupId($link));
    }
}
