<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FileField;
use Symfony\Component\HttpFoundation\File\File;

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

    public function testSubmitUploadsNewFiles()
    {
        $tmpDir = realpath(self::$tmpDir);
        $tmpName = md5(session_id() . '$secret$' . '12345');
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpName;
        $that = $this;

        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->once())
             ->method('move')
             ->with($this->equalTo($tmpDir));
        $file->expects($this->once())
             ->method('rename')
             ->with($this->equalTo($tmpName))
             ->will($this->returnCallback(function ($directory) use ($that, $tmpPath) {
                $that->createTmpFile($tmpPath);
             }));
        $file->expects($this->any())
             ->method('getName')
             ->will($this->returnValue('original_name.jpg'));

        $this->field->submit(array(
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

    public function testSubmitKeepsUploadedFilesOnErrors()
    {
        $tmpPath = self::$tmpDir . '/' . md5(session_id() . '$secret$' . '12345');
        $this->createTmpFile($tmpPath);

        $this->field->submit(array(
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

    /**
     * @expectedException UnexpectedValueException
     */
    public function testSubmitFailsOnMissingMultipart()
    {
        $this->field->submit(array(
            'file' => 'foo.jpg',
            'token' => '12345',
            'original_name' => 'original_name.jpg',
        ));
    }

    public function testSubmitKeepsOldFileIfNotOverwritten()
    {
        $oldPath = tempnam(sys_get_temp_dir(), 'FileFieldTest');
        $this->createTmpFile($oldPath);

        $this->field->setData($oldPath);

        $this->assertEquals($oldPath, $this->field->getData());

        $this->field->submit(array(
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

    public function testSubmitHandlesUploadErrIniSize()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_INI_SIZE));

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertTrue($this->field->isIniSizeExceeded());
    }

    public function testSubmitHandlesUploadErrFormSize()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_FORM_SIZE));

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertTrue($this->field->isFormSizeExceeded());
    }

    public function testSubmitHandlesUploadErrPartial()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_PARTIAL));

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));

        $this->assertFalse($this->field->isUploadComplete());
    }

    public function testSubmitThrowsExceptionOnUploadErrNoTmpDir()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_NO_TMP_DIR));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }

    public function testSubmitThrowsExceptionOnUploadErrCantWrite()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_CANT_WRITE));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }

    public function testSubmitThrowsExceptionOnUploadErrExtension()
    {
        $file = $this->getMock('Symfony\Component\HttpFoundation\File\UploadedFile', array(), array(), '', false);
        $file->expects($this->any())
             ->method('getError')
             ->will($this->returnValue(UPLOAD_ERR_EXTENSION));

        $this->setExpectedException('Symfony\Component\Form\Exception\FormException');

        $this->field->submit(array(
            'file' => $file,
            'token' => '12345',
            'original_name' => ''
        ));
    }
}