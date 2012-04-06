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
}
