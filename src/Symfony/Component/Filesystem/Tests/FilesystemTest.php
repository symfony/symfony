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
        var_dump(__METHOD__);
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.\DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.\DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $group = $this->getFileGroup($link);
        $groupId = $this->getFileGroupId($link);
        var_dump(chgrp($link, $group));
        var_dump(chgrp($link, $groupId));
        $this->assertFalse(true);

    }
}
