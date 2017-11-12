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
    public function testCopyCreatesNewFile(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyFails(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        $this->filesystem->copy($sourceFilePath, $targetFilePath);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyUnreadableFileFails(): void
    {
        // skip test on Windows; PHP can't easily set file as unreadable on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        // make sure target cannot be read
        $this->filesystem->chmod($sourceFilePath, 0222);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);
    }

    public function testCopyOverridesExistingFileIfModified(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        touch($targetFilePath, time() - 1000);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    public function testCopyDoesNotOverrideExistingFileByDefault(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('TARGET FILE', file_get_contents($targetFilePath));
    }

    public function testCopyOverridesExistingFileIfForced(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $this->filesystem->copy($sourceFilePath, $targetFilePath, true);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyWithOverrideWithReadOnlyTargetFails(): void
    {
        // skip test on Windows; PHP can't easily set file as unwritable on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        // make sure target is read-only
        $this->filesystem->chmod($targetFilePath, 0444);

        $this->filesystem->copy($sourceFilePath, $targetFilePath, true);
    }

    public function testCopyCreatesTargetDirectoryIfItDoesNotExist(): void
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFileDirectory = $this->workspace.DIRECTORY_SEPARATOR.'directory';
        $targetFilePath = $targetFileDirectory.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertTrue(is_dir($targetFileDirectory));
        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @group network
     */
    public function testCopyForOriginUrlsAndExistingLocalFileDefaultsToCopy(): void
    {
        $sourceFilePath = 'http://symfony.com/images/common/logo/logo_symfony_header.png';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($targetFilePath, 'TARGET FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath, false);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals(file_get_contents($sourceFilePath), file_get_contents($targetFilePath));
    }

    public function testMkdirCreatesDirectoriesRecursively(): void
    {
        $directory = $this->workspace
            .DIRECTORY_SEPARATOR.'directory'
            .DIRECTORY_SEPARATOR.'sub_directory';

        $this->filesystem->mkdir($directory);

        $this->assertTrue(is_dir($directory));
    }

    public function testMkdirCreatesDirectoriesFromArray(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $directories = array(
            $basePath.'1', $basePath.'2', $basePath.'3',
        );

        $this->filesystem->mkdir($directories);

        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));
    }

    public function testMkdirCreatesDirectoriesFromTraversableObject(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $directories = new \ArrayObject(array(
            $basePath.'1', $basePath.'2', $basePath.'3',
        ));

        $this->filesystem->mkdir($directories);

        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testMkdirCreatesDirectoriesFails(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $dir = $basePath.'2';

        file_put_contents($dir, '');

        $this->filesystem->mkdir($dir);
    }

    public function testTouchCreatesEmptyFile(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'1';

        $this->filesystem->touch($file);

        $this->assertFileExists($file);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testTouchFails(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'1'.DIRECTORY_SEPARATOR.'2';

        $this->filesystem->touch($file);
    }

    public function testTouchCreatesEmptyFilesFromArray(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $files = array(
            $basePath.'1', $basePath.'2', $basePath.'3',
        );

        $this->filesystem->touch($files);

        $this->assertFileExists($basePath.'1');
        $this->assertFileExists($basePath.'2');
        $this->assertFileExists($basePath.'3');
    }

    public function testTouchCreatesEmptyFilesFromTraversableObject(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $files = new \ArrayObject(array(
            $basePath.'1', $basePath.'2', $basePath.'3',
        ));

        $this->filesystem->touch($files);

        $this->assertFileExists($basePath.'1');
        $this->assertFileExists($basePath.'2');
        $this->assertFileExists($basePath.'3');
    }

    public function testRemoveCleansFilesAndDirectoriesIteratively(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR.'directory'.DIRECTORY_SEPARATOR;

        mkdir($basePath);
        mkdir($basePath.'dir');
        touch($basePath.'file');

        $this->filesystem->remove($basePath);

        $this->assertFileNotExists($basePath);
    }

    public function testRemoveCleansArrayOfFilesAndDirectories(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;

        mkdir($basePath.'dir');
        touch($basePath.'file');

        $files = array(
            $basePath.'dir', $basePath.'file',
        );

        $this->filesystem->remove($files);

        $this->assertFileNotExists($basePath.'dir');
        $this->assertFileNotExists($basePath.'file');
    }

    public function testRemoveCleansTraversableObjectOfFilesAndDirectories(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;

        mkdir($basePath.'dir');
        touch($basePath.'file');

        $files = new \ArrayObject(array(
            $basePath.'dir', $basePath.'file',
        ));

        $this->filesystem->remove($files);

        $this->assertFileNotExists($basePath.'dir');
        $this->assertFileNotExists($basePath.'file');
    }

    public function testRemoveIgnoresNonExistingFiles(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;

        mkdir($basePath.'dir');

        $files = array(
            $basePath.'dir', $basePath.'file',
        );

        $this->filesystem->remove($files);

        $this->assertFileNotExists($basePath.'dir');
    }

    public function testRemoveCleansInvalidLinks(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $basePath = $this->workspace.DIRECTORY_SEPARATOR.'directory'.DIRECTORY_SEPARATOR;

        mkdir($basePath);
        mkdir($basePath.'dir');
        // create symlink to nonexistent file
        @symlink($basePath.'file', $basePath.'file-link');

        // create symlink to dir using trailing forward slash
        $this->filesystem->symlink($basePath.'dir/', $basePath.'dir-link');
        $this->assertTrue(is_dir($basePath.'dir-link'));

        // create symlink to nonexistent dir
        rmdir($basePath.'dir');
        $this->assertFalse('\\' === DIRECTORY_SEPARATOR ? @readlink($basePath.'dir-link') : is_dir($basePath.'dir-link'));

        $this->filesystem->remove($basePath);

        $this->assertFileNotExists($basePath);
    }

    public function testFilesExists(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR.'directory'.DIRECTORY_SEPARATOR;

        mkdir($basePath);
        touch($basePath.'file1');
        mkdir($basePath.'folder');

        $this->assertTrue($this->filesystem->exists($basePath.'file1'));
        $this->assertTrue($this->filesystem->exists($basePath.'folder'));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testFilesExistsFails(): void
    {
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Long file names are an issue on Windows');
        }
        $basePath = $this->workspace.'\\directory\\';
        $maxPathLength = PHP_MAXPATHLEN - 2;

        $oldPath = getcwd();
        mkdir($basePath);
        chdir($basePath);
        $file = str_repeat('T', $maxPathLength - strlen($basePath) + 1);
        $path = $basePath.$file;
        exec('TYPE NUL >>'.$file); // equivalent of touch, we can not use the php touch() here because it suffers from the same limitation
        $this->longPathNamesWindows[] = $path; // save this so we can clean up later
        chdir($oldPath);
        $this->filesystem->exists($path);
    }

    public function testFilesExistsTraversableObjectOfFilesAndDirectories(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;

        mkdir($basePath.'dir');
        touch($basePath.'file');

        $files = new \ArrayObject(array(
            $basePath.'dir', $basePath.'file',
        ));

        $this->assertTrue($this->filesystem->exists($files));
    }

    public function testFilesNotExistsTraversableObjectOfFilesAndDirectories(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;

        mkdir($basePath.'dir');
        touch($basePath.'file');
        touch($basePath.'file2');

        $files = new \ArrayObject(array(
            $basePath.'dir', $basePath.'file', $basePath.'file2',
        ));

        unlink($basePath.'file');

        $this->assertFalse($this->filesystem->exists($files));
    }

    public function testInvalidFileNotExists(): void
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR.'directory'.DIRECTORY_SEPARATOR;

        $this->assertFalse($this->filesystem->exists($basePath.time()));
    }

    public function testChmodChangesFileMode(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $this->filesystem->chmod($file, 0400);
        $this->filesystem->chmod($dir, 0753);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(400, $file);
    }

    public function testChmodWithWrongModLeavesPreviousPermissionsUntouched(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'file';
        touch($dir);

        $permissions = fileperms($dir);

        $this->filesystem->chmod($dir, 'Wrongmode');

        $this->assertSame($permissions, fileperms($dir));
    }

    public function testChmodRecursive(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $this->filesystem->chmod($file, 0400, 0000, true);
        $this->filesystem->chmod($dir, 0753, 0000, true);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(753, $file);
    }

    public function testChmodAppliesUmask(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $this->filesystem->chmod($file, 0770, 0022);
        $this->assertFilePermissions(750, $file);
    }

    public function testChmodChangesModeOfArrayOfFiles(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace.DIRECTORY_SEPARATOR.'directory';
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $files = array($directory, $file);

        mkdir($directory);
        touch($file);

        $this->filesystem->chmod($files, 0753);

        $this->assertFilePermissions(753, $file);
        $this->assertFilePermissions(753, $directory);
    }

    public function testChmodChangesModeOfTraversableFileObject(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace.DIRECTORY_SEPARATOR.'directory';
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $files = new \ArrayObject(array($directory, $file));

        mkdir($directory);
        touch($file);

        $this->filesystem->chmod($files, 0753);

        $this->assertFilePermissions(753, $file);
        $this->assertFilePermissions(753, $directory);
    }

    public function testChmodChangesZeroModeOnSubdirectoriesOnRecursive(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $directory = $this->workspace.DIRECTORY_SEPARATOR.'directory';
        $subdirectory = $directory.DIRECTORY_SEPARATOR.'subdirectory';

        mkdir($directory);
        mkdir($subdirectory);
        chmod($subdirectory, 0000);

        $this->filesystem->chmod($directory, 0753, 0000, true);

        $this->assertFilePermissions(753, $subdirectory);
    }

    public function testChown(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $owner = $this->getFileOwner($dir);
        $this->filesystem->chown($dir, $owner);

        $this->assertSame($owner, $this->getFileOwner($dir));
    }

    public function testChownRecursive(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $owner = $this->getFileOwner($dir);
        $this->filesystem->chown($dir, $owner, true);

        $this->assertSame($owner, $this->getFileOwner($file));
    }

    public function testChownSymlink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $owner = $this->getFileOwner($link);
        $this->filesystem->chown($link, $owner);

        $this->assertSame($owner, $this->getFileOwner($link));
    }

    public function testChownLink(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $owner = $this->getFileOwner($link);
        $this->filesystem->chown($link, $owner);

        $this->assertSame($owner, $this->getFileOwner($link));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChownSymlinkFails(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $this->filesystem->chown($link, 'user'.time().mt_rand(1000, 9999));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChownLinkFails(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $this->filesystem->chown($link, 'user'.time().mt_rand(1000, 9999));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChownFail(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chown($dir, 'user'.time().mt_rand(1000, 9999));
    }

    public function testChgrp(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $group = $this->getFileGroup($dir);
        $this->filesystem->chgrp($dir, $group);

        $this->assertSame($group, $this->getFileGroup($dir));
    }

    public function testChgrpRecursive(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $group = $this->getFileGroup($dir);
        $this->filesystem->chgrp($dir, $group, true);

        $this->assertSame($group, $this->getFileGroup($file));
    }

    public function testChgrpSymlink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $group = $this->getFileGroup($link);
        $this->filesystem->chgrp($link, $group);

        $this->assertSame($group, $this->getFileGroup($link));
    }

    public function testChgrpLink(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $group = $this->getFileGroup($link);
        $this->filesystem->chgrp($link, $group);

        $this->assertSame($group, $this->getFileGroup($link));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChgrpSymlinkFails(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $this->filesystem->chgrp($link, 'user'.time().mt_rand(1000, 9999));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChgrpLinkFails(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $this->filesystem->chgrp($link, 'user'.time().mt_rand(1000, 9999));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChgrpFail(): void
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chgrp($dir, 'user'.time().mt_rand(1000, 9999));
    }

    public function testRename(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';
        touch($file);

        $this->filesystem->rename($file, $newPath);

        $this->assertFileNotExists($file);
        $this->assertFileExists($newPath);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testRenameThrowsExceptionIfTargetAlreadyExists(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';

        touch($file);
        touch($newPath);

        $this->filesystem->rename($file, $newPath);
    }

    public function testRenameOverwritesTheTargetIfItAlreadyExists(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';

        touch($file);
        touch($newPath);

        $this->filesystem->rename($file, $newPath, true);

        $this->assertFileNotExists($file);
        $this->assertFileExists($newPath);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testRenameThrowsExceptionOnError(): void
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.uniqid('fs_test_', true);
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';

        $this->filesystem->rename($file, $newPath);
    }

    public function testSymlink(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support creating "broken" symlinks');
        }

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        // $file does not exists right now: creating "broken" links is a wanted feature
        $this->filesystem->symlink($file, $link);

        $this->assertTrue(is_link($link));

        // Create the linked file AFTER creating the link
        touch($file);

        $this->assertEquals($file, readlink($link));
    }

    /**
     * @depends testSymlink
     */
    public function testRemoveSymlink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        $this->filesystem->remove($link);

        $this->assertTrue(!is_link($link));
        $this->assertTrue(!is_file($link));
        $this->assertTrue(!is_dir($link));
    }

    public function testSymlinkIsOverwrittenIfPointsToDifferentTarget(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);
        symlink($this->workspace, $link);

        $this->filesystem->symlink($file, $link);

        $this->assertTrue(is_link($link));
        $this->assertEquals($file, readlink($link));
    }

    public function testSymlinkIsNotOverwrittenIfAlreadyCreated(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);
        symlink($file, $link);

        $this->filesystem->symlink($file, $link);

        $this->assertTrue(is_link($link));
        $this->assertEquals($file, readlink($link));
    }

    public function testSymlinkCreatesTargetDirectoryIfItDoesNotExist(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link1 = $this->workspace.DIRECTORY_SEPARATOR.'dir'.DIRECTORY_SEPARATOR.'link';
        $link2 = $this->workspace.DIRECTORY_SEPARATOR.'dir'.DIRECTORY_SEPARATOR.'subdir'.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link1);
        $this->filesystem->symlink($file, $link2);

        $this->assertTrue(is_link($link1));
        $this->assertEquals($file, readlink($link1));
        $this->assertTrue(is_link($link2));
        $this->assertEquals($file, readlink($link2));
    }

    public function testLink(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);
        $this->filesystem->hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    /**
     * @depends testLink
     */
    public function testRemoveLink(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        $this->filesystem->remove($link);

        $this->assertTrue(!is_file($link));
    }

    public function testLinkIsOverwrittenIfPointsToDifferentTarget(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $file2 = $this->workspace.DIRECTORY_SEPARATOR.'file2';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);
        touch($file2);
        link($file2, $link);

        $this->filesystem->hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testLinkIsNotOverwrittenIfAlreadyCreated(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);
        link($file, $link);

        $this->filesystem->hardlink($file, $link);

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testLinkWithSeveralTargets(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link1 = $this->workspace.DIRECTORY_SEPARATOR.'link';
        $link2 = $this->workspace.DIRECTORY_SEPARATOR.'link2';

        touch($file);

        $this->filesystem->hardlink($file, array($link1, $link2));

        $this->assertTrue(is_file($link1));
        $this->assertEquals(fileinode($file), fileinode($link1));
        $this->assertTrue(is_file($link2));
        $this->assertEquals(fileinode($file), fileinode($link2));
    }

    public function testLinkWithSameTarget(): void
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        // practically same as testLinkIsNotOverwrittenIfAlreadyCreated
        $this->filesystem->hardlink($file, array($link, $link));

        $this->assertTrue(is_file($link));
        $this->assertEquals(fileinode($file), fileinode($link));
    }

    public function testReadRelativeLink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Relative symbolic links are not supported on Windows');
        }

        $file = $this->workspace.'/file';
        $link1 = $this->workspace.'/dir/link';
        $link2 = $this->workspace.'/dir/link2';
        touch($file);

        $this->filesystem->symlink('../file', $link1);
        $this->filesystem->symlink('link', $link2);

        $this->assertEquals($this->normalize('../file'), $this->filesystem->readlink($link1));
        $this->assertEquals('link', $this->filesystem->readlink($link2));
        $this->assertEquals($file, $this->filesystem->readlink($link1, true));
        $this->assertEquals($file, $this->filesystem->readlink($link2, true));
        $this->assertEquals($file, $this->filesystem->readlink($file, true));
    }

    public function testReadAbsoluteLink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->normalize($this->workspace.'/file');
        $link1 = $this->normalize($this->workspace.'/dir/link');
        $link2 = $this->normalize($this->workspace.'/dir/link2');
        touch($file);

        $this->filesystem->symlink($file, $link1);
        $this->filesystem->symlink($link1, $link2);

        $this->assertEquals($file, $this->filesystem->readlink($link1));
        $this->assertEquals($link1, $this->filesystem->readlink($link2));
        $this->assertEquals($file, $this->filesystem->readlink($link1, true));
        $this->assertEquals($file, $this->filesystem->readlink($link2, true));
        $this->assertEquals($file, $this->filesystem->readlink($file, true));
    }

    public function testReadBrokenLink(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support creating "broken" symlinks');
        }

        $file = $this->workspace.'/file';
        $link = $this->workspace.'/link';

        $this->filesystem->symlink($file, $link);

        $this->assertEquals($file, $this->filesystem->readlink($link));
        $this->assertNull($this->filesystem->readlink($link, true));

        touch($file);
        $this->assertEquals($file, $this->filesystem->readlink($link, true));
    }

    public function testReadLinkDefaultPathDoesNotExist(): void
    {
        $this->assertNull($this->filesystem->readlink($this->normalize($this->workspace.'/invalid')));
    }

    public function testReadLinkDefaultPathNotLink(): void
    {
        $file = $this->normalize($this->workspace.'/file');
        touch($file);

        $this->assertNull($this->filesystem->readlink($file));
    }

    public function testReadLinkCanonicalizePath(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->normalize($this->workspace.'/file');
        mkdir($this->normalize($this->workspace.'/dir'));
        touch($file);

        $this->assertEquals($file, $this->filesystem->readlink($this->normalize($this->workspace.'/dir/../file'), true));
    }

    public function testReadLinkCanonicalizedPathDoesNotExist(): void
    {
        $this->assertNull($this->filesystem->readlink($this->normalize($this->workspace.'invalid'), true));
    }

    /**
     * @dataProvider providePathsForMakePathRelative
     */
    public function testMakePathRelative($endPath, $startPath, $expectedPath): void
    {
        $path = $this->filesystem->makePathRelative($endPath, $startPath);

        $this->assertEquals($expectedPath, $path);
    }

    public function providePathsForMakePathRelative()
    {
        $paths = array(
            array('/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component', '../'),
            array('/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component/', '../'),
            array('/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component', '../'),
            array('/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component/', '../'),
            array('/usr/lib/symfony/', '/var/lib/symfony/src/Symfony/Component', '../../../../../../usr/lib/symfony/'),
            array('/var/lib/symfony/src/Symfony/', '/var/lib/symfony/', 'src/Symfony/'),
            array('/aa/bb', '/aa/bb', './'),
            array('/aa/bb', '/aa/bb/', './'),
            array('/aa/bb/', '/aa/bb', './'),
            array('/aa/bb/', '/aa/bb/', './'),
            array('/aa/bb/cc', '/aa/bb/cc/dd', '../'),
            array('/aa/bb/cc', '/aa/bb/cc/dd/', '../'),
            array('/aa/bb/cc/', '/aa/bb/cc/dd', '../'),
            array('/aa/bb/cc/', '/aa/bb/cc/dd/', '../'),
            array('/aa/bb/cc', '/aa', 'bb/cc/'),
            array('/aa/bb/cc', '/aa/', 'bb/cc/'),
            array('/aa/bb/cc/', '/aa', 'bb/cc/'),
            array('/aa/bb/cc/', '/aa/', 'bb/cc/'),
            array('/a/aab/bb', '/a/aa', '../aab/bb/'),
            array('/a/aab/bb', '/a/aa/', '../aab/bb/'),
            array('/a/aab/bb/', '/a/aa', '../aab/bb/'),
            array('/a/aab/bb/', '/a/aa/', '../aab/bb/'),
            array('/a/aab/bb/', '/', 'a/aab/bb/'),
            array('/a/aab/bb/', '/b/aab', '../../a/aab/bb/'),
            array('/aab/bb', '/aa', '../aab/bb/'),
            array('/aab', '/aa', '../aab/'),
            array('/aa/bb/cc', '/aa/dd/..', 'bb/cc/'),
            array('/aa/../bb/cc', '/aa/dd/..', '../bb/cc/'),
            array('/aa/bb/../../cc', '/aa/../dd/..', 'cc/'),
            array('/../aa/bb/cc', '/aa/dd/..', 'bb/cc/'),
            array('/../../aa/../bb/cc', '/aa/dd/..', '../bb/cc/'),
            array('C:/aa/bb/cc', 'C:/aa/dd/..', 'bb/cc/'),
            array('c:/aa/../bb/cc', 'c:/aa/dd/..', '../bb/cc/'),
            array('C:/aa/bb/../../cc', 'C:/aa/../dd/..', 'cc/'),
            array('C:/../aa/bb/cc', 'C:/aa/dd/..', 'bb/cc/'),
            array('C:/../../aa/../bb/cc', 'C:/aa/dd/..', '../bb/cc/'),
        );

        if ('\\' === DIRECTORY_SEPARATOR) {
            $paths[] = array('c:\var\lib/symfony/src/Symfony/', 'c:/var/lib/symfony/', 'src/Symfony/');
        }

        return $paths;
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\InvalidArgumentException
     * @expectedExceptionMessage The start path "var/lib/symfony/src/Symfony/Component" is not absolute.
     */
    public function testMakePathRelativeWithRelativeStartPath(): void
    {
        $this->assertSame('../../../', $this->filesystem->makePathRelative('/var/lib/symfony/', 'var/lib/symfony/src/Symfony/Component'));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\InvalidArgumentException
     * @expectedExceptionMessage The end path "var/lib/symfony/" is not absolute.
     */
    public function testMakePathRelativeWithRelativeEndPath(): void
    {
        $this->assertSame('../../../', $this->filesystem->makePathRelative('var/lib/symfony/', '/var/lib/symfony/src/Symfony/Component'));
    }

    public function testMirrorCopiesFilesAndDirectoriesRecursively(): void
    {
        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;
        $directory = $sourcePath.'directory'.DIRECTORY_SEPARATOR;
        $file1 = $directory.'file1';
        $file2 = $sourcePath.'file2';

        mkdir($sourcePath);
        mkdir($directory);
        file_put_contents($file1, 'FILE1');
        file_put_contents($file2, 'FILE2');

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertTrue(is_dir($targetPath.'directory'));
        $this->assertFileEquals($file1, $targetPath.'directory'.DIRECTORY_SEPARATOR.'file1');
        $this->assertFileEquals($file2, $targetPath.'file2');

        $this->filesystem->remove($file1);

        $this->filesystem->mirror($sourcePath, $targetPath, null, array('delete' => false));
        $this->assertTrue($this->filesystem->exists($targetPath.'directory'.DIRECTORY_SEPARATOR.'file1'));

        $this->filesystem->mirror($sourcePath, $targetPath, null, array('delete' => true));
        $this->assertFalse($this->filesystem->exists($targetPath.'directory'.DIRECTORY_SEPARATOR.'file1'));

        file_put_contents($file1, 'FILE1');

        $this->filesystem->mirror($sourcePath, $targetPath, null, array('delete' => true));
        $this->assertTrue($this->filesystem->exists($targetPath.'directory'.DIRECTORY_SEPARATOR.'file1'));

        $this->filesystem->remove($directory);
        $this->filesystem->mirror($sourcePath, $targetPath, null, array('delete' => true));
        $this->assertFalse($this->filesystem->exists($targetPath.'directory'));
        $this->assertFalse($this->filesystem->exists($targetPath.'directory'.DIRECTORY_SEPARATOR.'file1'));
    }

    public function testMirrorCreatesEmptyDirectory(): void
    {
        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath);

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));

        $this->filesystem->remove($sourcePath);
    }

    public function testMirrorCopiesLinks(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        file_put_contents($sourcePath.'file1', 'FILE1');
        symlink($sourcePath.'file1', $sourcePath.'link1');

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertFileEquals($sourcePath.'file1', $targetPath.'link1');
        $this->assertTrue(is_link($targetPath.DIRECTORY_SEPARATOR.'link1'));
    }

    public function testMirrorCopiesLinkedDirectoryContents(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing(true);

        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath.'nested/', 0777, true);
        file_put_contents($sourcePath.'/nested/file1.txt', 'FILE1');
        // Note: We symlink directory, not file
        symlink($sourcePath.'nested', $sourcePath.'link1');

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertFileEquals($sourcePath.'/nested/file1.txt', $targetPath.'link1/file1.txt');
        $this->assertTrue(is_link($targetPath.DIRECTORY_SEPARATOR.'link1'));
    }

    public function testMirrorCopiesRelativeLinkedContents(): void
    {
        $this->markAsSkippedIfSymlinkIsMissing(true);

        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;
        $oldPath = getcwd();

        mkdir($sourcePath.'nested/', 0777, true);
        file_put_contents($sourcePath.'/nested/file1.txt', 'FILE1');
        // Note: Create relative symlink
        chdir($sourcePath);
        symlink('nested', 'link1');

        chdir($oldPath);

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertFileEquals($sourcePath.'/nested/file1.txt', $targetPath.'link1/file1.txt');
        $this->assertTrue(is_link($targetPath.DIRECTORY_SEPARATOR.'link1'));
        $this->assertEquals('\\' === DIRECTORY_SEPARATOR ? realpath($sourcePath.'\nested') : 'nested', readlink($targetPath.DIRECTORY_SEPARATOR.'link1'));
    }

    public function testMirrorContentsWithSameNameAsSourceOrTargetWithoutDeleteOption(): void
    {
        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        touch($sourcePath.'source');
        touch($sourcePath.'target');

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $oldPath = getcwd();
        chdir($this->workspace);

        $this->filesystem->mirror('source', $targetPath);

        chdir($oldPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertFileExists($targetPath.'source');
        $this->assertFileExists($targetPath.'target');
    }

    public function testMirrorContentsWithSameNameAsSourceOrTargetWithDeleteOption(): void
    {
        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath);
        touch($sourcePath.'source');

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        mkdir($targetPath);
        touch($targetPath.'source');
        touch($targetPath.'target');

        $oldPath = getcwd();
        chdir($this->workspace);

        $this->filesystem->mirror('source', 'target', null, array('delete' => true));

        chdir($oldPath);

        $this->assertTrue(is_dir($targetPath));
        $this->assertFileExists($targetPath.'source');
        $this->assertFileNotExists($targetPath.'target');
    }

    /**
     * @dataProvider providePathsForIsAbsolutePath
     */
    public function testIsAbsolutePath($path, $expectedResult): void
    {
        $result = $this->filesystem->isAbsolutePath($path);

        $this->assertEquals($expectedResult, $result);
    }

    public function providePathsForIsAbsolutePath()
    {
        return array(
            array('/var/lib', true),
            array('c:\\\\var\\lib', true),
            array('\\var\\lib', true),
            array('var/lib', false),
            array('../var/lib', false),
            array('', false),
            array(null, false),
        );
    }

    public function testTempnam(): void
    {
        $dirname = $this->workspace;

        $filename = $this->filesystem->tempnam($dirname, 'foo');

        $this->assertFileExists($filename);
    }

    public function testTempnamWithFileScheme(): void
    {
        $scheme = 'file://';
        $dirname = $scheme.$this->workspace;

        $filename = $this->filesystem->tempnam($dirname, 'foo');

        $this->assertStringStartsWith($scheme, $filename);
        $this->assertFileExists($filename);
    }

    public function testTempnamWithMockScheme(): void
    {
        stream_wrapper_register('mock', 'Symfony\Component\Filesystem\Tests\Fixtures\MockStream\MockStream');

        $scheme = 'mock://';
        $dirname = $scheme.$this->workspace;

        $filename = $this->filesystem->tempnam($dirname, 'foo');

        $this->assertStringStartsWith($scheme, $filename);
        $this->assertFileExists($filename);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testTempnamWithZlibSchemeFails(): void
    {
        $scheme = 'compress.zlib://';
        $dirname = $scheme.$this->workspace;

        // The compress.zlib:// stream does not support mode x: creates the file, errors "failed to open stream: operation failed" and returns false
        $this->filesystem->tempnam($dirname, 'bar');
    }

    public function testTempnamWithPHPTempSchemeFails(): void
    {
        $scheme = 'php://temp';
        $dirname = $scheme;

        $filename = $this->filesystem->tempnam($dirname, 'bar');

        $this->assertStringStartsWith($scheme, $filename);

        // The php://temp stream deletes the file after close
        $this->assertFileNotExists($filename);
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testTempnamWithPharSchemeFails(): void
    {
        // Skip test if Phar disabled phar.readonly must be 0 in php.ini
        if (!\Phar::canWrite()) {
            $this->markTestSkipped('This test cannot run when phar.readonly is 1.');
        }

        $scheme = 'phar://';
        $dirname = $scheme.$this->workspace;
        $pharname = 'foo.phar';

        new \Phar($this->workspace.'/'.$pharname, 0, $pharname);
        // The phar:// stream does not support mode x: fails to create file, errors "failed to open stream: phar error: "$filename" is not a file in phar "$pharname"" and returns false
        $this->filesystem->tempnam($dirname, $pharname.'/bar');
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testTempnamWithHTTPSchemeFails(): void
    {
        $scheme = 'http://';
        $dirname = $scheme.$this->workspace;

        // The http:// scheme is read-only
        $this->filesystem->tempnam($dirname, 'bar');
    }

    public function testTempnamOnUnwritableFallsBackToSysTmp(): void
    {
        $scheme = 'file://';
        $dirname = $scheme.$this->workspace.DIRECTORY_SEPARATOR.'does_not_exist';

        $filename = $this->filesystem->tempnam($dirname, 'bar');
        $realTempDir = realpath(sys_get_temp_dir());
        $this->assertStringStartsWith(rtrim($scheme.$realTempDir, DIRECTORY_SEPARATOR), $filename);
        $this->assertFileExists($filename);

        // Tear down
        @unlink($filename);
    }

    public function testDumpFile(): void
    {
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        $this->filesystem->dumpFile($filename, 'bar');
        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }
    }

    public function testDumpFileOverwritesAnExistingFile(): void
    {
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo.txt';
        file_put_contents($filename, 'FOO BAR');

        $this->filesystem->dumpFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testDumpFileWithFileScheme(): void
    {
        $scheme = 'file://';
        $filename = $scheme.$this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';

        $this->filesystem->dumpFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testDumpFileWithZlibScheme(): void
    {
        $scheme = 'compress.zlib://';
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';

        $this->filesystem->dumpFile($filename, 'bar');

        // Zlib stat uses file:// wrapper so remove scheme
        $this->assertFileExists(str_replace($scheme, '', $filename));
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testAppendToFile(): void
    {
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'bar.txt';

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        $this->filesystem->dumpFile($filename, 'foo');

        $this->filesystem->appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('foobar', file_get_contents($filename));

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename, 'The written file should keep the same permissions as before.');
            umask($oldMask);
        }
    }

    public function testAppendToFileWithScheme(): void
    {
        $scheme = 'file://';
        $filename = $scheme.$this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';
        $this->filesystem->dumpFile($filename, 'foo');

        $this->filesystem->appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('foobar', file_get_contents($filename));
    }

    public function testAppendToFileWithZlibScheme(): void
    {
        $scheme = 'compress.zlib://';
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';
        $this->filesystem->dumpFile($filename, 'foo');

        // Zlib stat uses file:// wrapper so remove it
        $this->assertSame('foo', file_get_contents(str_replace($scheme, '', $filename)));

        $this->filesystem->appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('foobar', file_get_contents($filename));
    }

    public function testAppendToFileCreateTheFileIfNotExists(): void
    {
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'bar.txt';

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $oldMask = umask(0002);
        }

        $this->filesystem->appendToFile($filename, 'bar');

        // skip mode check on Windows
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->assertFilePermissions(664, $filename);
            umask($oldMask);
        }

        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testDumpKeepsExistingPermissionsWhenOverwritingAnExistingFile(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo.txt';
        file_put_contents($filename, 'FOO BAR');
        chmod($filename, 0745);

        $this->filesystem->dumpFile($filename, 'bar', null);

        $this->assertFilePermissions(745, $filename);
    }

    public function testCopyShouldKeepExecutionPermission(): void
    {
        $this->markAsSkippedIfChmodIsMissing();

        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        chmod($sourceFilePath, 0745);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFilePermissions(767, $targetFilePath);
    }

    /**
     * Normalize the given path (transform each blackslash into a real directory separator).
     */
    private function normalize(string $path): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
