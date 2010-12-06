<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Form\FileField;

class FileFieldTest extends \PHPUnit_Framework_TestCase
{
    public static $tmpFiles = array();

    protected static $tmpDir;

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir();

        // we need a session ID
        @session_start();
    }

    protected function tearDown()
    {
        foreach (self::$tmpFiles as $key => $file) {
            @unlink($file);
            unset(self::$tmpFiles[$key]);
        }
    }

    public function createTmpFile($path)
    {
        self::$tmpFiles[] = $path;
        file_put_contents($path, 'foobar');
    }

    public function testBindUploadsNewFiles()
    {
        $field = new FileField('file', array(
            'secret' => '$secret$',
            'tmp_dir' => self::$tmpDir,
        ));

        $tmpPath = realpath(self::$tmpDir) . '/' . md5(session_id() . '$secret$' . '12345');
        $that = $this;

        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->once())
             ->method('move')
             ->with($this->equalTo($tmpPath))
             ->will($this->returnCallback(function ($path) use ($that) {
                $that->createTmpFile($path);
             }));
        $file->expects($this->any())
             ->method('getOriginalName')
             ->will($this->returnValue('original_name.jpg'));

        $field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => '',
        ));

        $this->assertTrue(file_exists($tmpPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ), $field->getDisplayedData());
        $this->assertEquals($tmpPath, $field->getData());
    }

    public function testBindKeepsUploadedFilesOnErrors()
    {
        $field = new FileField('file', array(
            'secret' => '$secret$',
            'tmp_dir' => self::$tmpDir,
        ));

        $tmpPath = self::$tmpDir . '/' . md5(session_id() . '$secret$' . '12345');
        $this->createTmpFile($tmpPath);

        $field->bind(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ));

        $this->assertTrue(file_exists($tmpPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ), $field->getDisplayedData());
        $this->assertEquals(realpath($tmpPath), realpath($field->getData()));
    }

    public function testBindKeepsOldFileIfNotOverwritten()
    {
        $field = new FileField('file', array(
            'secret' => '$secret$',
            'tmp_dir' => self::$tmpDir,
        ));

        $oldPath = tempnam(sys_get_temp_dir(), 'FileFieldTest');
        $this->createTmpFile($oldPath);

        $field->setData($oldPath);

        $this->assertEquals($oldPath, $field->getData());

        $field->bind(array(
            'file' => '',
            'token' => '12345',
            'original_name' => '',
        ));

        $this->assertTrue(file_exists($oldPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => '',
        ), $field->getDisplayedData());
        $this->assertEquals($oldPath, $field->getData());
    }
}