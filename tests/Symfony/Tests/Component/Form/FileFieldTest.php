<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FileField;

class FileFieldTest extends \PHPUnit_Framework_TestCase
{
    public static $tmpFiles = array();

    protected static $tmpDir;

    protected $field;

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir();

        // we need a session ID
        @session_start();
    }

    protected function setUp()
    {
        $this->field = new FileField('file', array(
            'secret' => '$secret$',
            'tmp_dir' => self::$tmpDir,
        ));
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

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => '',
        ));

        $this->assertTrue(file_exists($tmpPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ), $this->field->getDisplayedData());
        $this->assertEquals($tmpPath, $this->field->getData());
        $this->assertFalse($this->field->isIniSizeExceeded());
        $this->assertFalse($this->field->isFormSizeExceeded());
        $this->assertTrue($this->field->isUploadComplete());
    }

    public function testBindKeepsUploadedFilesOnErrors()
    {
        $tmpPath = self::$tmpDir . '/' . md5(session_id() . '$secret$' . '12345');
        $this->createTmpFile($tmpPath);

        $this->field->bind(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ));

        $this->assertTrue(file_exists($tmpPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ), $this->field->getDisplayedData());
        $this->assertEquals(realpath($tmpPath), realpath($this->field->getData()));
    }

    public function testBindKeepsOldFileIfNotOverwritten()
    {
        $oldPath = tempnam(sys_get_temp_dir(), 'FileFieldTest');
        $this->createTmpFile($oldPath);

        $this->field->setData($oldPath);

        $this->assertEquals($oldPath, $this->field->getData());

        $this->field->bind(array(
            'file' => '',
            'token' => '12345',
            'original_name' => '',
        ));

        $this->assertTrue(file_exists($oldPath));
        $this->assertEquals(array(
            'file' => '',
            'token' => '12345',
            'original_name' => '',
        ), $this->field->getDisplayedData());
        $this->assertEquals($oldPath, $this->field->getData());
    }

    public function testBindHandlesUploadErrIniSize()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_INI_SIZE));

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertTrue($this->field->isIniSizeExceeded());
    }

    public function testBindHandlesUploadErrFormSize()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_FORM_SIZE));

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertTrue($this->field->isFormSizeExceeded());
    }

    public function testBindHandlesUploadErrPartial()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_PARTIAL));

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertFalse($this->field->isUploadComplete());
    }

    public function testBindThrowsExceptionOnUploadErrNoTmpDir()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_NO_TMP_DIR));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }

    public function testBindThrowsExceptionOnUploadErrCantWrite()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_CANT_WRITE));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }

    public function testBindThrowsExceptionOnUploadErrExtension()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_EXTENSION));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->bind(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }
}