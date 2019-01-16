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

use Symfony\Component\Form\NativeRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NativeRequestHandlerTest extends AbstractRequestHandlerTest
{
    private static $serverBackup;

    public static function setUpBeforeClass()
    {
        self::$serverBackup = $_SERVER;
    }

    protected function setUp()
    {
        parent::setUp();

        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER = [
            // PHPUnit needs this entry
            'SCRIPT_NAME' => self::$serverBackup['SCRIPT_NAME'],
        ];
    }

    protected function tearDown()
    {
        parent::tearDown();

        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER = self::$serverBackup;
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testRequestShouldBeNull()
    {
        $this->requestHandler->handleRequest($this->getMockForm('name', 'GET'), 'request');
    }

    public function testMethodOverrideHeaderTakesPrecedenceIfPost()
    {
        $form = $this->getMockForm('param1', 'PUT');

        $this->setRequestData('POST', [
            'param1' => 'DATA',
        ]);

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $form->expects($this->once())
            ->method('submit')
            ->with('DATA');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testConvertEmptyUploadedFilesToNull()
    {
        $form = $this->getMockForm('param1', 'POST', false);

        $this->setRequestData('POST', [], ['param1' => [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]]);

        $form->expects($this->once())
            ->method('submit')
            ->with($this->identicalTo(null));

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testFixBuggyFilesArray()
    {
        $form = $this->getMockForm('param1', 'POST', false);

        $this->setRequestData('POST', [], ['param1' => [
            'name' => [
                'field' => 'upload.txt',
            ],
            'type' => [
                'field' => 'text/plain',
            ],
            'tmp_name' => [
                'field' => 'owfdskjasdfsa',
            ],
            'error' => [
                'field' => UPLOAD_ERR_OK,
            ],
            'size' => [
                'field' => 100,
            ],
        ]]);

        $form->expects($this->once())
            ->method('submit')
            ->with([
                'field' => [
                    'name' => 'upload.txt',
                    'type' => 'text/plain',
                    'tmp_name' => 'owfdskjasdfsa',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 100,
                ],
            ]);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testFixBuggyNestedFilesArray()
    {
        $form = $this->getMockForm('param1', 'POST');

        $this->setRequestData('POST', [], ['param1' => [
            'name' => [
                'field' => ['subfield' => 'upload.txt'],
            ],
            'type' => [
                'field' => ['subfield' => 'text/plain'],
            ],
            'tmp_name' => [
                'field' => ['subfield' => 'owfdskjasdfsa'],
            ],
            'error' => [
                'field' => ['subfield' => UPLOAD_ERR_OK],
            ],
            'size' => [
                'field' => ['subfield' => 100],
            ],
        ]]);

        $form->expects($this->once())
            ->method('submit')
            ->with([
                'field' => [
                    'subfield' => [
                        'name' => 'upload.txt',
                        'type' => 'text/plain',
                        'tmp_name' => 'owfdskjasdfsa',
                        'error' => UPLOAD_ERR_OK,
                        'size' => 100,
                    ],
                ],
            ]);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testMethodOverrideHeaderIgnoredIfNotPost()
    {
        $form = $this->getMockForm('param1', 'POST');

        $this->setRequestData('GET', [
                'param1' => 'DATA',
            ]);

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    protected function setRequestData($method, $data, $files = [])
    {
        if ('GET' === $method) {
            $_GET = $data;
            $_FILES = [];
        } else {
            $_POST = $data;
            $_FILES = $files;
        }

        $_SERVER = [
            'REQUEST_METHOD' => $method,
            // PHPUnit needs this entry
            'SCRIPT_NAME' => self::$serverBackup['SCRIPT_NAME'],
        ];
    }

    protected function getRequestHandler()
    {
        return new NativeRequestHandler($this->serverParams);
    }

    protected function getMockFile($suffix = '')
    {
        return [
            'name' => 'upload'.$suffix.'.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa'.$suffix,
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ];
    }

    protected function getInvalidFile()
    {
        return [
            'name' => 'upload.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => '0',
            'size' => '100',
        ];
    }
}
