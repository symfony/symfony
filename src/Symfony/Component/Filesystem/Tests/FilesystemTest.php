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

use Symfony\Component\Filesystem\Tests\Fixtures\StringishObject;

/**
 * Test class for Filesystem.
 */
class FilesystemTest extends FilesystemTestCase
{
    public function provideSourceTargetPaths()
    {
        $workspace = $this->createWorkspace();

        return array(
            array($workspace.DIRECTORY_SEPARATOR.'copy_source_file', $workspace.DIRECTORY_SEPARATOR.'copy_target_file'),
            array(
                new StringishObject($workspace.DIRECTORY_SEPARATOR.'copy_source_file_v2'),
                new StringishObject($workspace.DIRECTORY_SEPARATOR.'copy_target_file_v2'),
            ),
            array(
                new StringishObject($workspace.DIRECTORY_SEPARATOR.'copy_source_file_v3'),
                $workspace.DIRECTORY_SEPARATOR.'copy_target_file_v3',
            ),
            array(
                $workspace.DIRECTORY_SEPARATOR.'copy_source_file_v4',
                new StringishObject($workspace.DIRECTORY_SEPARATOR.'copy_target_file_v4'),
            ),
        );
    }

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyCreatesNewFile($sourceFilePath, $targetFilePath)
    {
        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @dataProvider provideSourceTargetPaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyFails($sourceFilePath, $targetFilePath)
    {
        $this->filesystem->copy($sourceFilePath, $targetFilePath);
    }

    /**
     * @dataProvider provideSourceTargetPaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyUnreadableFileFails($sourceFilePath, $targetFilePath)
    {
        // skip test on Windows; PHP can't easily set file as unreadable on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        // make sure target cannot be read
        $this->filesystem->chmod($sourceFilePath, 0222);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);
    }

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyOverridesExistingFileIfModified($sourceFilePath, $targetFilePath)
    {
        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        touch($targetFilePath, time() - 1000);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyDoesNotOverrideExistingFileByDefault($sourceFilePath, $targetFilePath)
    {
        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals('TARGET FILE', file_get_contents($targetFilePath));
    }

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyOverridesExistingFileIfForced($sourceFilePath, $targetFilePath)
    {
        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');

        // make sure both files have the same modification time
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $this->filesystem->copy($sourceFilePath, $targetFilePath, true);

        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @dataProvider provideSourceTargetPaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testCopyWithOverrideWithReadOnlyTargetFails($sourceFilePath, $targetFilePath)
    {
        // skip test on Windows; PHP can't easily set file as unwritable on Windows
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

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

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyCreatesTargetDirectoryIfItDoesNotExist($sourceFilePath, $targetFilePath)
    {
        $targetFileDirectory = dirname($sourceFilePath).DIRECTORY_SEPARATOR.'directory';
        if ($targetFilePath instanceof StringishObject) {
            $targetFilePath = new StringishObject($targetFileDirectory.DIRECTORY_SEPARATOR.'copy_target_file');
        } else {
            $targetFilePath = $targetFileDirectory.DIRECTORY_SEPARATOR.'copy_target_file';
        }

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertTrue(is_dir($targetFileDirectory));
        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    /**
     * @dataProvider provideSourceTargetPaths
     */
    public function testCopyForOriginUrlsAndExistingLocalFileDefaultsToCopy($sourceFilePath, $targetFilePath)
    {
        if ($sourceFilePath instanceof StringishObject) {
            $sourceFilePath = new StringishObject('http://symfony.com/images/common/logo/logo_symfony_header.png');
        } else {
            $sourceFilePath = 'http://symfony.com/images/common/logo/logo_symfony_header.png';
        }

        file_put_contents($targetFilePath, 'TARGET FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath, false);

        $this->assertFileExists((string) $targetFilePath);
        $this->assertEquals(file_get_contents($sourceFilePath), file_get_contents($targetFilePath));
    }

    public function provideSubPaths()
    {
        $workspace = $this->createWorkspace();
        $basePath = $workspace.DIRECTORY_SEPARATOR.'directory';

        return array(
            array($basePath.DIRECTORY_SEPARATOR.'1', $basePath),
            array(new StringishObject($basePath.DIRECTORY_SEPARATOR.'2'), new StringishObject($basePath)),
        );
    }

    /**
     * @dataProvider provideSubPaths
     */
    public function testMkdirCreatesDirectoriesRecursively($directory)
    {
        $this->filesystem->mkdir($directory);

        $this->assertTrue(is_dir($directory));
    }

    public function provideIterablePaths()
    {
        $workspace = $this->createWorkspace();
        $files = array(
            $workspace.DIRECTORY_SEPARATOR.'1', $workspace.DIRECTORY_SEPARATOR.'2', new StringishObject($workspace.DIRECTORY_SEPARATOR.'3'),
        );

        return array(
            array($files),
            array(new \ArrayObject($files)),
        );
    }

    /**
     * @dataProvider provideIterablePaths
     */
    public function testMkdirCreatesDirectoriesFromIterable($directories)
    {
        $this->filesystem->mkdir($directories);

        foreach ($directories as $directory) {
            $this->assertTrue(is_dir($directory));
        }
    }

    public function providePaths()
    {
        $workspace = $this->createWorkspace();

        return array(
            array($workspace.DIRECTORY_SEPARATOR.'1'),
            array(new StringishObject($workspace.DIRECTORY_SEPARATOR.'2')),
        );
    }

    /**
     * @dataProvider providePaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testMkdirCreatesDirectoriesFails($dir)
    {
        file_put_contents($dir, '');

        $this->filesystem->mkdir($dir);
    }

    /**
     * @dataProvider providePaths
     */
    public function testTouchCreatesEmptyFile($file)
    {
        $this->filesystem->touch($file);

        $this->assertFileExists((string) $file);
    }

    /**
     * @dataProvider provideSubPaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testTouchFails($file)
    {
        $this->filesystem->touch($file);
    }

    /**
     * @dataProvider provideIterablePaths
     */
    public function testTouchCreatesEmptyFilesFromIterable($files)
    {
        $this->filesystem->touch($files);

        foreach ($files as $file) {
            $this->assertFileExists((string) $file);
        }
    }

    public function provideExistingSubPaths()
    {
        $workspace = $this->createWorkspace();
        $basePath = $workspace.DIRECTORY_SEPARATOR.'directory';
        mkdir($basePath);
        mkdir($basePath.DIRECTORY_SEPARATOR.'1');
        touch($basePath.DIRECTORY_SEPARATOR.'2');
        mkdir($basePath.DIRECTORY_SEPARATOR.'3');
        touch($basePath.DIRECTORY_SEPARATOR.'4');

        return array(
            array($basePath.DIRECTORY_SEPARATOR.'1', $basePath),
            array(new StringishObject($basePath.DIRECTORY_SEPARATOR.'2'), new StringishObject($basePath)),
            array($basePath.DIRECTORY_SEPARATOR.'3', $basePath),
            array(new StringishObject($basePath.DIRECTORY_SEPARATOR.'4'), new StringishObject($basePath)),
        );
    }

    /**
     * @dataProvider provideExistingSubPaths
     */
    public function testRemoveCleansFilesAndDirectoriesIteratively($path, $basePath)
    {
        $this->filesystem->remove($basePath);

        $this->assertFileNotExists((string) $basePath);
    }

    public function provideExistingIterablePaths()
    {
        $workspace = $this->createWorkspace();
        $files = array(
            $workspace.DIRECTORY_SEPARATOR.'1', new StringishObject($workspace.DIRECTORY_SEPARATOR.'2'), $workspace.DIRECTORY_SEPARATOR.'3', new StringishObject($workspace.DIRECTORY_SEPARATOR.'4'),
        );

        mkdir($workspace.DIRECTORY_SEPARATOR.'1');
        touch($workspace.DIRECTORY_SEPARATOR.'2');
        mkdir($workspace.DIRECTORY_SEPARATOR.'3');
        touch($workspace.DIRECTORY_SEPARATOR.'4');

        return array(
            array($files),
            array(new \ArrayObject($files)),
        );
    }

    /**
     * @dataProvider provideExistingIterablePaths
     */
    public function testRemoveCleansIterableOfFilesAndDirectories($files)
    {
        $this->filesystem->remove($files);

        foreach ($files as $file) {
            $this->assertFileNotExists((string) $file);
        }
    }

    public function providePartialExistingIterablePaths()
    {
        $workspace = $this->createWorkspace();
        $files = array(
            $workspace.DIRECTORY_SEPARATOR.'1', new StringishObject($workspace.DIRECTORY_SEPARATOR.'2'), $workspace.DIRECTORY_SEPARATOR.'3', new StringishObject($workspace.DIRECTORY_SEPARATOR.'4'),
        );
        $exists = array(
            $workspace.DIRECTORY_SEPARATOR.'2', $workspace.DIRECTORY_SEPARATOR.'3',
        );
        $rest = array(
            $workspace.DIRECTORY_SEPARATOR.'1', $workspace.DIRECTORY_SEPARATOR.'4',
        );

        touch($workspace.DIRECTORY_SEPARATOR.'2');
        mkdir($workspace.DIRECTORY_SEPARATOR.'3');

        return array(
            array($files, $exists, $rest),
            array(new \ArrayObject($files), $exists, $rest),
        );
    }

    /**
     * @dataProvider providePartialExistingIterablePaths
     */
    public function testRemoveIgnoresNonExistingFiles($files, array $exists)
    {
        $this->filesystem->remove($files);

        foreach ($exists as $file) {
            $this->assertFileNotExists((string) $file);
        }
    }

    /**
     * @dataProvider providePaths
     */
    public function testRemoveCleansInvalidLinks($basePath)
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        mkdir($basePath);
        mkdir($basePath.DIRECTORY_SEPARATOR.'dir');
        // create symlink to nonexistent file
        @symlink($basePath.DIRECTORY_SEPARATOR.'file', $basePath.DIRECTORY_SEPARATOR.'file-link');

        // create symlink to dir using trailing forward slash
        $this->filesystem->symlink($basePath.DIRECTORY_SEPARATOR.'dir/', $basePath.DIRECTORY_SEPARATOR.'dir-link');
        $this->assertTrue(is_dir($basePath.DIRECTORY_SEPARATOR.'dir-link'));

        // create symlink to nonexistent dir
        rmdir($basePath.DIRECTORY_SEPARATOR.'dir');
        $this->assertFalse('\\' === DIRECTORY_SEPARATOR ? @readlink($basePath.DIRECTORY_SEPARATOR.'dir-link') : is_dir($basePath.DIRECTORY_SEPARATOR.'dir-link'));

        $this->filesystem->remove($basePath);

        $this->assertFileNotExists((string) $basePath);
    }

    /**
     * @dataProvider provideExistingSubPaths
     */
    public function testFilesExists($path)
    {
        $this->assertTrue($this->filesystem->exists($path));
    }

    /**
     * @dataProvider providePaths
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testFilesExistsFailsWithLongPathNames($basePath)
    {
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Test covers edge case on Windows only.');
        }

        $oldPath = getcwd();
        mkdir($basePath);
        chdir($basePath);
        $file = str_repeat('T', 259 - strlen($basePath));
        $path = $basePath.$file;
        exec('TYPE NUL >>'.$file); // equivalent of touch, we can not use the php touch() here because it suffers from the same limitation
        $this->longPathNamesWindows[] = $path; // save this so we can clean up later
        chdir($oldPath);

        if ($basePath instanceof StringishObject) {
            $path = new StringishObject($path);
        }
        $this->filesystem->exists($path);
    }

    /**
     * @dataProvider provideExistingIterablePaths
     */
    public function testFilesExistsWithIterableOfExistingFilesAndDirectories($files)
    {
        $this->assertTrue($this->filesystem->exists($files));
    }

    /**
     * @dataProvider providePartialExistingIterablePaths
     */
    public function testFilesNotExistsWithIterableOfPartialExistingFilesAndDirectories($files)
    {
        $this->assertFalse($this->filesystem->exists($files));
    }

    /**
     * @dataProvider providePaths
     */
    public function testInvalidFileNotExists($basePath)
    {
        if ($basePath instanceof StringishObject) {
            $basePath = new StringishObject($basePath.DIRECTORY_SEPARATOR.time());
        } else {
            $basePath = $basePath.DIRECTORY_SEPARATOR.time();
        }
        $this->assertFalse($this->filesystem->exists($basePath));
    }

    /**
     * @dataProvider provideSubPaths
     */
    public function testChmodChangesFileMode($file, $dir)
    {
        $this->markAsSkippedIfChmodIsMissing();

        mkdir($dir);
        touch($file);

        $this->filesystem->chmod($file, 0400);
        $this->filesystem->chmod($dir, 0753);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(400, $file);

        // prevent existing parent directory from this test in subsequent datasets
        $this->filesystem->remove($dir);
    }

    /**
     * @dataProvider providePaths
     */
    public function testChmodWrongMod($dir)
    {
        $this->markAsSkippedIfChmodIsMissing();

        touch($dir);

        $this->filesystem->chmod($dir, 'Wrongmode');
    }

    /**
     * @dataProvider provideSubPaths
     */
    public function testChmodRecursive($file, $dir)
    {
        $this->markAsSkippedIfChmodIsMissing();

        mkdir($dir);
        touch($file);

        $this->filesystem->chmod($file, 0400, 0000, true);
        $this->filesystem->chmod($dir, 0753, 0000, true);

        $this->assertFilePermissions(753, $dir);
        $this->assertFilePermissions(753, $file);

        // prevent existing parent directory from this test in subsequent datasets
        $this->filesystem->remove($dir);
    }

    /**
     * @dataProvider providePaths
     */
    public function testChmodAppliesUmask($file)
    {
        $this->markAsSkippedIfChmodIsMissing();

        touch($file);

        $this->filesystem->chmod($file, 0770, 0022);
        $this->assertFilePermissions(750, $file);
    }

    /**
     * @dataProvider provideExistingIterablePaths
     */
    public function testChmodChangesModeOfIterableOfFiles($files)
    {
        $this->markAsSkippedIfChmodIsMissing();

        $this->filesystem->chmod($files, 0753);

        foreach ($files as $file) {
            $this->assertFilePermissions(753, $file);
        }
    }

    /**
     * @dataProvider provideSubPaths
     */
    public function testChmodChangesZeroModeOnSubdirectoriesOnRecursive($subdirectory, $directory)
    {
        $this->markAsSkippedIfChmodIsMissing();

        mkdir($directory);
        mkdir($subdirectory);
        chmod($subdirectory, 0000);

        $this->filesystem->chmod($directory, 0753, 0000, true);

        $this->assertFilePermissions(753, $subdirectory);

        // prevent existing parent directory from this test in subsequent datasets
        $this->filesystem->remove($directory);
    }

    public function testChown()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chown($dir, $this->getFileOwner($dir));
    }

    public function testChownRecursive()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $this->filesystem->chown($dir, $this->getFileOwner($dir), true);
    }

    public function testChownSymlink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $this->filesystem->chown($link, $this->getFileOwner($link));
    }

    public function testChownLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $this->filesystem->chown($link, $this->getFileOwner($link));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChownSymlinkFails()
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
    public function testChownLinkFails()
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
    public function testChownFail()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chown($dir, 'user'.time().mt_rand(1000, 9999));
    }

    public function testChgrp()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chgrp($dir, $this->getFileGroup($dir));
    }

    public function testChgrpRecursive()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);
        $file = $dir.DIRECTORY_SEPARATOR.'file';
        touch($file);

        $this->filesystem->chgrp($dir, $this->getFileGroup($dir), true);
    }

    public function testChgrpSymlink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->symlink($file, $link);

        $this->filesystem->chgrp($link, $this->getFileGroup($link));
    }

    public function testChgrpLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        touch($file);

        $this->filesystem->hardlink($file, $link);

        $this->filesystem->chgrp($link, $this->getFileGroup($link));
    }

    /**
     * @expectedException \Symfony\Component\Filesystem\Exception\IOException
     */
    public function testChgrpSymlinkFails()
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
    public function testChgrpLinkFails()
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
    public function testChgrpFail()
    {
        $this->markAsSkippedIfPosixIsMissing();

        $dir = $this->workspace.DIRECTORY_SEPARATOR.'dir';
        mkdir($dir);

        $this->filesystem->chgrp($dir, 'user'.time().mt_rand(1000, 9999));
    }

    public function testRename()
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
    public function testRenameThrowsExceptionIfTargetAlreadyExists()
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'file';
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';

        touch($file);
        touch($newPath);

        $this->filesystem->rename($file, $newPath);
    }

    public function testRenameOverwritesTheTargetIfItAlreadyExists()
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
    public function testRenameThrowsExceptionOnError()
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.uniqid('fs_test_', true);
        $newPath = $this->workspace.DIRECTORY_SEPARATOR.'new_file';

        $this->filesystem->rename($file, $newPath);
    }

    public function testSymlink()
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
    public function testRemoveSymlink()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        $this->filesystem->remove($link);

        $this->assertTrue(!is_link($link));
        $this->assertTrue(!is_file($link));
        $this->assertTrue(!is_dir($link));
    }

    public function testSymlinkIsOverwrittenIfPointsToDifferentTarget()
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

    public function testSymlinkIsNotOverwrittenIfAlreadyCreated()
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

    public function testSymlinkCreatesTargetDirectoryIfItDoesNotExist()
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

    public function testLink()
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
    public function testRemoveLink()
    {
        $this->markAsSkippedIfLinkIsMissing();

        $link = $this->workspace.DIRECTORY_SEPARATOR.'link';

        $this->filesystem->remove($link);

        $this->assertTrue(!is_file($link));
    }

    public function testLinkIsOverwrittenIfPointsToDifferentTarget()
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

    public function testLinkIsNotOverwrittenIfAlreadyCreated()
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

    public function testLinkWithSeveralTargets()
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

    public function testLinkWithSameTarget()
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

    public function testReadRelativeLink()
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

    public function testReadAbsoluteLink()
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

    public function testReadBrokenLink()
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

    public function testReadLinkDefaultPathDoesNotExist()
    {
        $this->assertNull($this->filesystem->readlink($this->normalize($this->workspace.'/invalid')));
    }

    public function testReadLinkDefaultPathNotLink()
    {
        $file = $this->normalize($this->workspace.'/file');
        touch($file);

        $this->assertNull($this->filesystem->readlink($file));
    }

    public function testReadLinkCanonicalizePath()
    {
        $this->markAsSkippedIfSymlinkIsMissing();

        $file = $this->normalize($this->workspace.'/file');
        mkdir($this->normalize($this->workspace.'/dir'));
        touch($file);

        $this->assertEquals($file, $this->filesystem->readlink($this->normalize($this->workspace.'/dir/../file'), true));
    }

    public function testReadLinkCanonicalizedPathDoesNotExist()
    {
        $this->assertNull($this->filesystem->readlink($this->normalize($this->workspace.'invalid'), true));
    }

    /**
     * @dataProvider providePathsForMakePathRelative
     */
    public function testMakePathRelative($endPath, $startPath, $expectedPath)
    {
        $path = $this->filesystem->makePathRelative($endPath, $startPath);

        $this->assertEquals($expectedPath, $path);
    }

    /**
     * @return array
     */
    public function providePathsForMakePathRelative()
    {
        $paths = array(
            array('/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component', '../'),
            array('/var/lib/symfony/src/Symfony/', '/var/lib/symfony/src/Symfony/Component/', '../'),
            array('/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component', '../'),
            array('/var/lib/symfony/src/Symfony', '/var/lib/symfony/src/Symfony/Component/', '../'),
            array('var/lib/symfony/', 'var/lib/symfony/src/Symfony/Component', '../../../'),
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
        );

        if ('\\' === DIRECTORY_SEPARATOR) {
            $paths[] = array('c:\var\lib/symfony/src/Symfony/', 'c:/var/lib/symfony/', 'src/Symfony/');
        }

        return $paths;
    }

    public function testMirrorCopiesFilesAndDirectoriesRecursively()
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

    public function testMirrorCreatesEmptyDirectory()
    {
        $sourcePath = $this->workspace.DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR;

        mkdir($sourcePath);

        $targetPath = $this->workspace.DIRECTORY_SEPARATOR.'target'.DIRECTORY_SEPARATOR;

        $this->filesystem->mirror($sourcePath, $targetPath);

        $this->assertTrue(is_dir($targetPath));

        $this->filesystem->remove($sourcePath);
    }

    public function testMirrorCopiesLinks()
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

    public function testMirrorCopiesLinkedDirectoryContents()
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

    public function testMirrorCopiesRelativeLinkedContents()
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

    /**
     * @dataProvider providePathsForIsAbsolutePath
     */
    public function testIsAbsolutePath($path, $expectedResult)
    {
        $result = $this->filesystem->isAbsolutePath($path);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
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

    public function testTempnam()
    {
        $dirname = $this->workspace;

        $filename = $this->filesystem->tempnam($dirname, 'foo');

        $this->assertFileExists($filename);
    }

    public function testTempnamWithFileScheme()
    {
        $scheme = 'file://';
        $dirname = $scheme.$this->workspace;

        $filename = $this->filesystem->tempnam($dirname, 'foo');

        $this->assertStringStartsWith($scheme, $filename);
        $this->assertFileExists($filename);
    }

    public function testTempnamWithMockScheme()
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
    public function testTempnamWithZlibSchemeFails()
    {
        $scheme = 'compress.zlib://';
        $dirname = $scheme.$this->workspace;

        // The compress.zlib:// stream does not support mode x: creates the file, errors "failed to open stream: operation failed" and returns false
        $this->filesystem->tempnam($dirname, 'bar');
    }

    public function testTempnamWithPHPTempSchemeFails()
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
    public function testTempnamWithPharSchemeFails()
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
    public function testTempnamWithHTTPSchemeFails()
    {
        $scheme = 'http://';
        $dirname = $scheme.$this->workspace;

        // The http:// scheme is read-only
        $this->filesystem->tempnam($dirname, 'bar');
    }

    public function testTempnamOnUnwritableFallsBackToSysTmp()
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

    public function testDumpFile()
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

    public function testDumpFileOverwritesAnExistingFile()
    {
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo.txt';
        file_put_contents($filename, 'FOO BAR');

        $this->filesystem->dumpFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testDumpFileWithFileScheme()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM does not handle the file:// scheme correctly');
        }

        $scheme = 'file://';
        $filename = $scheme.$this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';

        $this->filesystem->dumpFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testDumpFileWithZlibScheme()
    {
        $scheme = 'compress.zlib://';
        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';

        $this->filesystem->dumpFile($filename, 'bar');

        // Zlib stat uses file:// wrapper so remove scheme
        $this->assertFileExists(str_replace($scheme, '', $filename));
        $this->assertSame('bar', file_get_contents($filename));
    }

    public function testAppendToFile()
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

    public function testAppendToFileWithScheme()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM does not handle the file:// scheme correctly');
        }

        $scheme = 'file://';
        $filename = $scheme.$this->workspace.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'baz.txt';
        $this->filesystem->dumpFile($filename, 'foo');

        $this->filesystem->appendToFile($filename, 'bar');

        $this->assertFileExists($filename);
        $this->assertSame('foobar', file_get_contents($filename));
    }

    public function testAppendToFileWithZlibScheme()
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

    public function testAppendToFileCreateTheFileIfNotExists()
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

    public function testDumpKeepsExistingPermissionsWhenOverwritingAnExistingFile()
    {
        $this->markAsSkippedIfChmodIsMissing();

        $filename = $this->workspace.DIRECTORY_SEPARATOR.'foo.txt';
        file_put_contents($filename, 'FOO BAR');
        chmod($filename, 0745);

        $this->filesystem->dumpFile($filename, 'bar', null);

        $this->assertFilePermissions(745, $filename);
    }

    public function testCopyShouldKeepExecutionPermission()
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
     *
     * @param string $path
     *
     * @return string
     */
    private function normalize($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
