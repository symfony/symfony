<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\NativeFormProcessor;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NativeFormProcessorTest extends AbstractFormProcessorTest
{
    private static $serverBackup;

    public static function setUpBeforeClass()
    {
        self::$serverBackup = $_SERVER;
    }

    protected function setUp()
    {
        parent::setUp();

        $_GET = array();
        $_POST = array();
        $_FILES = array();
        $_SERVER = array(
            // PHPUnit needs this entry
            'SCRIPT_NAME' => self::$serverBackup['SCRIPT_NAME'],
        );
    }

    protected function tearDown()
    {
        parent::tearDown();

        $_GET = array();
        $_POST = array();
        $_FILES = array();
        $_SERVER = self::$serverBackup;
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldBeNull()
    {
        $this->processor->processForm($this->getMockForm('name', 'GET'), 'request');
    }

    public function testMethodOverrideHeaderTakesPrecedenceIfPost()
    {
        $form = $this->getMockForm('param1', 'PUT');

        $this->setRequestData('POST', array(
            'param1' => 'DATA',
        ));

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $form->expects($this->once())
            ->method('bind')
            ->with('DATA');

        $this->processor->processForm($form, $this->request);
    }

    public function testConvertEmptyUploadedFilesToNull()
    {
        $form = $this->getMockForm('param1', 'POST', false);

        $this->setRequestData('POST', array(), array('param1' => array(
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0
        )));

        $form->expects($this->once())
            ->method('bind')
            ->with($this->identicalTo(null));

        $this->processor->processForm($form, $this->request);
    }

    public function testFixBuggyFilesArray()
    {
        $form = $this->getMockForm('param1', 'POST', false);

        $this->setRequestData('POST', array(), array('param1' => array(
            'name' => array(
                'field' => 'upload.txt',
            ),
            'type' => array(
                'field' => 'text/plain',
            ),
            'tmp_name' => array(
                'field' => 'owfdskjasdfsa',
            ),
            'error' => array(
                'field' => UPLOAD_ERR_OK,
            ),
            'size' => array(
                'field' => 100,
            ),
        )));

        $form->expects($this->once())
            ->method('bind')
            ->with(array(
                'field' => array(
                    'name' => 'upload.txt',
                    'type' => 'text/plain',
                    'tmp_name' => 'owfdskjasdfsa',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 100,
                ),
            ));

        $this->processor->processForm($form, $this->request);
    }

    public function testFixBuggyNestedFilesArray()
    {
        $form = $this->getMockForm('param1', 'POST');

        $this->setRequestData('POST', array(), array('param1' => array(
            'name' => array(
                'field' => array('subfield' => 'upload.txt'),
            ),
            'type' => array(
                'field' => array('subfield' => 'text/plain'),
            ),
            'tmp_name' => array(
                'field' => array('subfield' => 'owfdskjasdfsa'),
            ),
            'error' => array(
                'field' => array('subfield' => UPLOAD_ERR_OK),
            ),
            'size' => array(
                'field' => array('subfield' => 100),
            ),
        )));

        $form->expects($this->once())
            ->method('bind')
            ->with(array(
                'field' => array(
                    'subfield' => array(
                        'name' => 'upload.txt',
                        'type' => 'text/plain',
                        'tmp_name' => 'owfdskjasdfsa',
                        'error' => UPLOAD_ERR_OK,
                        'size' => 100,
                    ),
                ),
            ));

        $this->processor->processForm($form, $this->request);
    }

    public function testMethodOverrideHeaderIgnoredIfNotPost()
    {
        $form = $this->getMockForm('param1', 'POST');

        $this->setRequestData('GET', array(
                'param1' => 'DATA',
            ));

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $form->expects($this->never())
            ->method('bind');

        $this->processor->processForm($form, $this->request);
    }

    protected function setRequestData($method, $data, $files = array())
    {
        if ('GET' === $method) {
            $_GET = $data;
            $_FILES = array();
        } else {
            $_POST = $data;
            $_FILES = $files;
        }

        $_SERVER = array(
            'REQUEST_METHOD' => $method,
            // PHPUnit needs this entry
            'SCRIPT_NAME' => self::$serverBackup['SCRIPT_NAME'],
        );
    }

    protected function getFormProcessor()
    {
        return new NativeFormProcessor();
    }

    protected function getMockFile()
    {
        return array(
            'name' => 'upload.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        );
    }
}
