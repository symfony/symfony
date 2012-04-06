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
    public function testCopyCreatesNewFile()
    {
        $sourceFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $filesystem = new Filesystem();
        $filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));

        unlink($sourceFilePath);
        unlink($targetFilePath);
    }

    public function testCopyOverridesExistingFileIfModified()
    {
        $sourceFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        touch($targetFilePath, time() - 1000);

        $filesystem = new Filesystem();
        $filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));

        unlink($sourceFilePath);
        unlink($targetFilePath);
    }

    public function testCopyDoesNotOverrideExistingFileByDefault()
    {
        $sourceFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $filesystem = new Filesystem();
        $filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('TARGET FILE', file_get_contents($targetFilePath));

        unlink($sourceFilePath);
        unlink($targetFilePath);
    }

    public function testCopyOverridesExistingFileIfForced()
    {
        $sourceFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');
        file_put_contents($targetFilePath, 'TARGET FILE');
        $modificationTime = time() - 1000;
        touch($sourceFilePath, $modificationTime);
        touch($targetFilePath, $modificationTime);

        $filesystem = new Filesystem();
        $filesystem->copy($sourceFilePath, $targetFilePath, true);

        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));

        unlink($sourceFilePath);
        unlink($targetFilePath);
    }

    public function testCopyCreatesTargetDirectoryIfItDoesNotExist()
    {
        $sourceFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'copy_source_file';
        $targetFileDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.time();
        $targetFilePath = $targetFileDirectory.DIRECTORY_SEPARATOR.'copy_target_file';

        file_put_contents($sourceFilePath, 'SOURCE FILE');

        $filesystem = new Filesystem();
        $filesystem->copy($sourceFilePath, $targetFilePath);

        $this->assertTrue(is_dir($targetFileDirectory));
        $this->assertFileExists($targetFilePath);
        $this->assertEquals('SOURCE FILE', file_get_contents($targetFilePath));

        unlink($sourceFilePath);
        unlink($targetFilePath);
        rmdir($targetFileDirectory);
    }

    public function testMkdirCreatesDirectoriesRecursively()
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.time();
        $subDirectory = $directory.DIRECTORY_SEPARATOR.'sub_directory';

        $filesystem = new Filesystem();
        $result = $filesystem->mkdir($subDirectory);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($subDirectory));

        rmdir($subDirectory);
        rmdir($directory);
    }

    public function testMkdirCreatesDirectoriesFromArray()
    {
        $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.time();
        $directories = array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        );

        $filesystem = new Filesystem();
        $result = $filesystem->mkdir($directories);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));

        rmdir($basePath.'1');
        rmdir($basePath.'2');
        rmdir($basePath.'3');
    }

    public function testMkdirCreatesDirectoriesFromTraversableObject()
    {
        $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.time();
        $directories = new \ArrayObject(array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        ));

        $filesystem = new Filesystem();
        $result = $filesystem->mkdir($directories);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertTrue(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));

        rmdir($basePath.'1');
        rmdir($basePath.'2');
        rmdir($basePath.'3');
    }

    public function testMkdirCreatesDirectoriesEvenIfItFailsToCreateOneOfThem()
    {
        $basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.time();
        $directories = array(
            $basePath.'1', $basePath.'2', $basePath.'3'
        );

        // create a file to make that directory cannot be created
        file_put_contents($basePath.'2', '');

        $filesystem = new Filesystem();
        $result = $filesystem->mkdir($directories);

        $this->assertFalse($result);
        $this->assertTrue(is_dir($basePath.'1'));
        $this->assertFalse(is_dir($basePath.'2'));
        $this->assertTrue(is_dir($basePath.'3'));

        rmdir($basePath.'1');
        unlink($basePath.'2');
        rmdir($basePath.'3');
    }
}
