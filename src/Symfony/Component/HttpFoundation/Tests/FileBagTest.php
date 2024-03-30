<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

/**
 * FileBagTest.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class FileBagTest extends TestCase
{
    public function testFileMustBeAnArrayOrUploadedFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        new FileBag(['file' => 'foo']);
    }

    public function testShouldConvertsUploadedFiles()
    {
        $tmpFile = $this->createTempFile();
        $name = basename($tmpFile);

        $file = new UploadedFile($tmpFile, $name, 'text/plain');

        $bag = new FileBag(['file' => [
            'name' => $name,
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => 0,
            'size' => null,
        ]]);

        $this->assertEquals($file, $bag->get('file'));
    }

    public function testShouldSetEmptyUploadedFilesToNull()
    {
        $bag = new FileBag(['file' => [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => \UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]]);

        $this->assertNull($bag->get('file'));
    }

    public function testShouldRemoveEmptyUploadedFilesForMultiUpload()
    {
        $bag = new FileBag(['files' => [
            'name' => [''],
            'type' => [''],
            'tmp_name' => [''],
            'error' => [\UPLOAD_ERR_NO_FILE],
            'size' => [0],
        ]]);

        $this->assertSame([], $bag->get('files'));
    }

    public function testShouldNotRemoveEmptyUploadedFilesForAssociativeArray()
    {
        $bag = new FileBag(['files' => [
            'name' => ['file1' => ''],
            'type' => ['file1' => ''],
            'tmp_name' => ['file1' => ''],
            'error' => ['file1' => \UPLOAD_ERR_NO_FILE],
            'size' => ['file1' => 0],
        ]]);

        $this->assertSame(['file1' => null], $bag->get('files'));
    }

    public function testShouldConvertUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $name = basename($tmpFile);
        $file = new UploadedFile($tmpFile, $name, 'text/plain');

        $bag = new FileBag([
            'child' => [
                'name' => [
                    'file' => $name,
                ],
                'type' => [
                    'file' => 'text/plain',
                ],
                'tmp_name' => [
                    'file' => $tmpFile,
                ],
                'error' => [
                    'file' => 0,
                ],
                'size' => [
                    'file' => null,
                ],
            ],
        ]);

        $files = $bag->all();
        $this->assertEquals($file, $files['child']['file']);
    }

    public function testShouldConvertNestedUploadedFilesWithPhpBug()
    {
        $tmpFile = $this->createTempFile();
        $name = basename($tmpFile);
        $file = new UploadedFile($tmpFile, $name, 'text/plain');

        $bag = new FileBag([
            'child' => [
                'name' => [
                    'sub' => ['file' => $name],
                ],
                'type' => [
                    'sub' => ['file' => 'text/plain'],
                ],
                'tmp_name' => [
                    'sub' => ['file' => $tmpFile],
                ],
                'error' => [
                    'sub' => ['file' => 0],
                ],
                'size' => [
                    'sub' => ['file' => null],
                ],
            ],
        ]);

        $files = $bag->all();
        $this->assertEquals($file, $files['child']['sub']['file']);
    }

    public function testShouldNotConvertNestedUploadedFiles()
    {
        $tmpFile = $this->createTempFile();
        $name = basename($tmpFile);
        $file = new UploadedFile($tmpFile, $name, 'text/plain');
        $bag = new FileBag(['image' => ['file' => $file]]);

        $files = $bag->all();
        $this->assertEquals($file, $files['image']['file']);
    }

    public function testWebkitDirectoryUpload()
    {
        $file1 = __DIR__.'/File/Fixtures/webkitdirectory/test.txt';
        $file2 = __DIR__.'/File/Fixtures/webkitdirectory/nested/test.txt';

        $bag = new FileBag([
            'child' => [
                'name' => [
                    'test.txt',
                    'test.txt',
                ],
                'full_path' => [
                    'webkitdirectory/test.txt',
                    'webkitdirectory/nested/test.txt',
                ],
                'type' => [
                    'text/plain',
                    'text/plain',
                ],
                'tmp_name' => [
                    $file1,
                    $file2,
                ],
                'error' => [
                    0, 0,
                ],
                'size' => [
                    null, null,
                ],
            ],
        ]);

        /** @var UploadedFile[] */
        $files = $bag->get('child');

        $this->assertEquals('test.txt', $files[0]->getClientOriginalName());
        $this->assertEquals('test.txt', $files[1]->getClientOriginalName());

        $this->assertEquals('webkitdirectory/test.txt', $files[0]->getClientOriginalPath());
        $this->assertEquals('webkitdirectory/nested/test.txt', $files[1]->getClientOriginalPath());
    }

    protected function createTempFile()
    {
        $tempFile = tempnam(sys_get_temp_dir().'/form_test', 'FormTest');
        file_put_contents($tempFile, '1');

        return $tempFile;
    }

    protected function setUp(): void
    {
        mkdir(sys_get_temp_dir().'/form_test', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir().'/form_test/*') as $file) {
            @unlink($file);
        }

        @rmdir(sys_get_temp_dir().'/form_test');
    }
}
