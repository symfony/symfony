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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\NativeRequestHandler;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NativeRequestHandlerTest extends AbstractRequestHandlerTestCase
{
    private static array $serverBackup;

    public static function setUpBeforeClass(): void
    {
        self::$serverBackup = $_SERVER;
    }

    protected function setUp(): void
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

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER = self::$serverBackup;
    }

    public function testRequestShouldBeNull()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->requestHandler->handleRequest($this->createForm('name', 'GET'), 'request');
    }

    public function testMethodOverrideHeaderTakesPrecedenceIfPost()
    {
        $form = $this->createForm('param1', 'PUT');

        $this->setRequestData('POST', [
            'param1' => 'DATA',
        ]);

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('DATA', $form->getData());
    }

    public function testConvertEmptyUploadedFilesToNull()
    {
        $form = $this->createForm('param1', 'POST', false);

        $this->setRequestData('POST', [], ['param1' => [
            'name' => '',
            'type' => '',
            'tmp_name' => '',
            'error' => \UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ]]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertNull($form->getData());
    }

    public function testFixBuggyFilesArray()
    {
        $form = $this->createForm('param1', 'POST', true);
        $fieldForm = $this->createBuilder('field', false, ['allow_file_upload' => true])->getForm();
        $form->add($fieldForm);

        $this->setRequestData('POST', [], ['param1' => [
            'name' => [
                'field' => 'upload.txt',
            ],
            'full_path' => [
                'field' => 'path/to/file/upload.txt',
            ],
            'type' => [
                'field' => 'text/plain',
            ],
            'tmp_name' => [
                'field' => 'owfdskjasdfsa',
            ],
            'error' => [
                'field' => \UPLOAD_ERR_OK,
            ],
            'size' => [
                'field' => 100,
            ],
        ]]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertEquals([
            'name' => 'upload.txt',
            'full_path' => 'path/to/file/upload.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => \UPLOAD_ERR_OK,
            'size' => 100,
        ], $fieldForm->getData());
    }

    public function testFixBuggyNestedFilesArray()
    {
        $form = $this->createForm('param1', 'POST', true);
        $fieldForm = $this->createForm('field', null, true);
        $form->add($fieldForm);
        $subfieldForm = $this->createBuilder('subfield', false, ['allow_file_upload' => true])->getForm();
        $fieldForm->add($subfieldForm);

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
                'field' => ['subfield' => \UPLOAD_ERR_OK],
            ],
            'size' => [
                'field' => ['subfield' => 100],
            ],
        ]]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($subfieldForm->isSubmitted());
        $this->assertEquals([
            'name' => 'upload.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => \UPLOAD_ERR_OK,
            'size' => 100,
        ], $subfieldForm->getData());
    }

    public function testMethodOverrideHeaderIgnoredIfNotPost()
    {
        $form = $this->createForm('param1', 'POST');

        $this->setRequestData('GET', [
            'param1' => 'DATA',
        ]);

        $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'PUT';

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    public function testFormIgnoresMethodFieldIfRequestMethodIsMatched()
    {
        $form = $this->createForm('foo', 'PUT', true);
        $form->add($this->createForm('bar'));

        $this->setRequestData('PUT', [
            'foo' => [
                '_method' => 'PUT',
                'bar' => 'baz',
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertSame([], $form->getExtraData());
    }

    public function testFormDoesNotIgnoreMethodFieldIfRequestMethodIsNotMatched()
    {
        $form = $this->createForm('foo', 'PUT', true);
        $form->add($this->createForm('bar'));

        $this->setRequestData('PUT', [
            'foo' => [
                '_method' => 'DELETE',
                'bar' => 'baz',
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertSame(['_method' => 'DELETE'], $form->getExtraData());
    }

    public function testMethodSubFormIsSubmitted()
    {
        $form = $this->createForm('foo', 'PUT', true);
        $form->add($this->createForm('_method'));
        $form->add($this->createForm('bar'));

        $this->setRequestData('PUT', [
            'foo' => [
                '_method' => 'PUT',
                'bar' => 'baz',
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->get('_method')->isSubmitted());
        $this->assertSame('PUT', $form->get('_method')->getData());
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

    protected function getUploadedFile($suffix = '')
    {
        return [
            'name' => 'upload'.$suffix.'.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa'.$suffix,
            'error' => \UPLOAD_ERR_OK,
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

    protected function getFailedUploadedFile($errorCode)
    {
        return [
            'name' => 'upload.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => $errorCode,
            'size' => 100,
        ];
    }
}
