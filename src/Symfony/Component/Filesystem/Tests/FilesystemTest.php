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

use Symfony\Component\Filesystem\Filesystem;

/**
 * Test class for Filesystem.
 */
class FilesystemTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string $workspace
     */
    private $workspace = null;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem $filesystem
     */
    private $filesystem = null;

    public function setUp()
    {
        $this->filesystem = new Filesystem();
        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.time().rand(0, 1000);
        mkdir($this->workspace, 0777, true);
    }

    public function tearDown()
    {
        $this->clean($this->workspace);
    }

    /**
     * @param string $file
     */
    private function clean($file)
    {
        if (!file_exists($file)) {
            return;
        }

        if (is_dir($file) && !is_link($file)) {
            $dir = new \FilesystemIterator($file);
            foreach ($dir as $childFile) {
                $this->clean($childFile);
            }

            rmdir($file);
        } else {
            unlink($file);
        }
    }

    public function testCopyCreatesNewFile()
    {
        $sourceFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = $this->workspace.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $this->filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));
    }

    public function testCopyOverridesExistingFileIfModified()
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

    public function testCopyDoesNotOverrideExistingFileByDefault()
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

    public function testCopyOverridesExistingFileIfForced()
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

    public function testCopyCreatesTargetDirectoryIfItDoesNotExist()
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

    public function testMkdirCreatesDirectoriesRecursively()
    {
        $directory = $this->workspace
            .DIRECTORY_SEPARATOR.'directory'
            .DIRECTORY_SEPARATOR.'sub_directory';

        $result = $this->filesystem->mkdir($directory);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($directory));
    }

    public function testMkdirCreatesDirectoriesFromArray()
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $directories = array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        );

        $result = $this->filesystem->mkdir($directories);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));
    }

    public function testMkdirCreatesDirectoriesFromTraversableObject()
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $directories = new \ArrayObject(array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        ));

        $result = $this->filesystem->mkdir($directories);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));
    }

    public function testMkdirCreatesDirectoriesEvenIfItFailsToCreateOneOfThem()
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $directories = array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        );

        // create a file to make that directory cannot be created
        file_put_contents($basePath.'2', '');

        $result = $this->filesystem->mkdir($directories);

        $this->assertFalse($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertFalse(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));
    }

    public function testTouchCreatesEmptyFile()
    {
        $file = $this->workspace.DIRECTORY_SEPARATOR.'1';

        $this->filesystem->touch($file);

        $this->assertFileExists($file);
    }

    public function testTouchCreatesEmptyFilesFromArray()
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $files = array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        );

        $this->filesystem->touch($files);

        $this->assertFileExists($basePath.'1');
        $this->assertFileExists($basePath.'2');
        $this->assertFileExists($basePath.'3');
    }

    public function testTouchCreatesEmptyFilesFromTraversableObject()
    {
        $basePath = $this->workspace.DIRECTORY_SEPARATOR;
        $files = new \ArrayObject(array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        ));

        $this->filesystem->touch($files);

        $this->assertFileExists($basePath.'1');
        $this->assertFileExists($basePath.'2');
        $this->assertFileExists($basePath.'3');
    }
}
