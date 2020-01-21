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
        var_dump(__LINE__);

        $file = $this->workspace.\DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.\DIRECTORY_SEPARATOR.'link';
        var_dump(__LINE__);

        touch($file);
        var_dump(__LINE__);

        $this->filesystem->symlink($file, $link);
        var_dump(__LINE__);

        $group = $this->getFileGroup($link);
        var_dump(__LINE__);
        $groupId = $this->getFileGroupId($link);
        var_dump(__LINE__);
        var_dump(chgrp($link, $group));
        var_dump(__LINE__);
        var_dump(chgrp($link, $groupId));
        var_dump(__LINE__);
        $this->assertFalse(true);
        var_dump(__LINE__);

    }
}
