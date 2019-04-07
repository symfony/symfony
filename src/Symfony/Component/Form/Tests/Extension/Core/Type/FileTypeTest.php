<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\NativeRequestHandler;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\TranslatorInterface;

class FileTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\FileType';

    protected function getExtensions()
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)->getMock();
        $translator->expects($this->any())->method('trans')->willReturnArgument(0);

        return array_merge(parent::getExtensions(), [new CoreExtension(null, null, $translator)]);
    }

    // https://github.com/symfony/symfony/pull/5028
    public function testSetData()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->getForm();
        $data = new File(__DIR__.'/../../../Fixtures/foo', false);

        $form->setData($data);

        // Ensures the data class is defined to accept File instance
        $this->assertSame($data, $form->getData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testSubmit(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->setRequestHandler($requestHandler)->getForm();
        $data = $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo.jpg');

        $form->submit($data);

        $this->assertSame($data, $form->getData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testSetDataMultiple(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'multiple' => true,
        ])->setRequestHandler($requestHandler)->getForm();

        $data = [
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo.jpg'),
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo2', 'foo2.jpg'),
        ];

        $form->setData($data);
        $this->assertSame($data, $form->getData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testSubmitMultiple(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, [
            'multiple' => true,
        ])->setRequestHandler($requestHandler)->getForm();

        $data = [
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo.jpg'),
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo2', 'foo2.jpg'),
        ];

        $form->submit($data);
        $this->assertSame($data, $form->getData());

        $view = $form->createView();
        $this->assertSame('file[]', $view->vars['full_name']);
        $this->assertArrayHasKey('multiple', $view->vars['attr']);
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testDontPassValueToView(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->setRequestHandler($requestHandler)->getForm();
        $form->submit([
            'file' => $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo.jpg'),
        ]);

        $this->assertEquals('', $form->createView()->vars['value']);
    }

    public function testPassMultipartFalseToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertTrue($view->vars['multipart']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullWhenMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
        ]);
        // submitted data when an input file is uploaded without choosing any file
        $form->submit([null]);

        $this->assertSame([], $form->getData());
        $this->assertSame([], $form->getNormData());
        $this->assertSame([], $form->getViewData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testSubmittedFilePathsAreDropped(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->setRequestHandler($requestHandler)->getForm();
        $form->submit('file:///etc/passwd');

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testMultipleSubmittedFilePathsAreDropped(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, [
                'multiple' => true,
            ])
            ->setRequestHandler($requestHandler)
            ->getForm();
        $form->submit([
            'file:///etc/passwd',
            $this->createUploadedFile(new HttpFoundationRequestHandler(), __DIR__.'/../../../Fixtures/foo', 'foo.jpg'),
            $this->createUploadedFile(new NativeRequestHandler(), __DIR__.'/../../../Fixtures/foo2', 'foo2.jpg'),
        ]);

        $this->assertCount(1, $form->getData());
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testSubmitNonArrayValueWhenMultiple(RequestHandlerInterface $requestHandler)
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, [
                'multiple' => true,
            ])
            ->setRequestHandler($requestHandler)
            ->getForm();
        $form->submit(null);

        $this->assertSame([], $form->getData());
        $this->assertSame([], $form->getNormData());
        $this->assertSame([], $form->getViewData());
    }

    public function requestHandlerProvider()
    {
        return [
            [new HttpFoundationRequestHandler()],
            [new NativeRequestHandler()],
        ];
    }

    /**
     * @dataProvider uploadFileErrorCodes
     */
    public function testFailedFileUploadIsTurnedIntoFormErrorUsingHttpFoundationRequestHandler($errorCode, $expectedErrorMessage)
    {
        $requestHandler = new HttpFoundationRequestHandler();
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE)
            ->setRequestHandler($requestHandler)
            ->getForm();
        $form->submit($this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo', $errorCode));

        if (UPLOAD_ERR_OK === $errorCode) {
            $this->assertTrue($form->isValid());
        } else {
            $this->assertFalse($form->isValid());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[0]->getMessage());
        }
    }

    /**
     * @dataProvider uploadFileErrorCodes
     */
    public function testFailedFileUploadIsTurnedIntoFormErrorUsingNativeRequestHandler($errorCode, $expectedErrorMessage)
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE)
            ->setRequestHandler(new NativeRequestHandler())
            ->getForm();
        $form->submit([
            'name' => 'foo.txt',
            'type' => 'text/plain',
            'tmp_name' => 'owfdskjasdfsa',
            'error' => $errorCode,
            'size' => 100,
        ]);

        if (UPLOAD_ERR_OK === $errorCode) {
            $this->assertTrue($form->isValid());
        } else {
            $this->assertFalse($form->isValid());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[0]->getMessage());
        }
    }

    /**
     * @dataProvider uploadFileErrorCodes
     */
    public function testMultipleSubmittedFailedFileUploadsAreTurnedIntoFormErrorUsingHttpFoundationRequestHandler($errorCode, $expectedErrorMessage)
    {
        $requestHandler = new HttpFoundationRequestHandler();
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, [
                'multiple' => true,
            ])
            ->setRequestHandler($requestHandler)
            ->getForm();
        $form->submit([
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'foo', $errorCode),
            $this->createUploadedFile($requestHandler, __DIR__.'/../../../Fixtures/foo', 'bar', $errorCode),
        ]);

        if (UPLOAD_ERR_OK === $errorCode) {
            $this->assertTrue($form->isValid());
        } else {
            $this->assertFalse($form->isValid());
            $this->assertCount(2, $form->getErrors());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[0]->getMessage());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[1]->getMessage());
        }
    }

    /**
     * @dataProvider uploadFileErrorCodes
     */
    public function testMultipleSubmittedFailedFileUploadsAreTurnedIntoFormErrorUsingNativeRequestHandler($errorCode, $expectedErrorMessage)
    {
        $form = $this->factory
            ->createBuilder(static::TESTED_TYPE, null, [
                'multiple' => true,
            ])
            ->setRequestHandler(new NativeRequestHandler())
            ->getForm();
        $form->submit([
            [
                'name' => 'foo.txt',
                'type' => 'text/plain',
                'tmp_name' => 'owfdskjasdfsa',
                'error' => $errorCode,
                'size' => 100,
            ],
            [
                'name' => 'bar.txt',
                'type' => 'text/plain',
                'tmp_name' => 'owfdskjasdfsa',
                'error' => $errorCode,
                'size' => 100,
            ],
        ]);

        if (UPLOAD_ERR_OK === $errorCode) {
            $this->assertTrue($form->isValid());
        } else {
            $this->assertFalse($form->isValid());
            $this->assertCount(2, $form->getErrors());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[0]->getMessage());
            $this->assertSame($expectedErrorMessage, $form->getErrors()[1]->getMessage());
        }
    }

    public function uploadFileErrorCodes()
    {
        return [
            'no error' => [UPLOAD_ERR_OK, null],
            'upload_max_filesize ini directive' => [UPLOAD_ERR_INI_SIZE, 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.'],
            'MAX_FILE_SIZE from form' => [UPLOAD_ERR_FORM_SIZE, 'The file is too large.'],
            'partially uploaded' => [UPLOAD_ERR_PARTIAL, 'The file could not be uploaded.'],
            'no file upload' => [UPLOAD_ERR_NO_FILE, 'The file could not be uploaded.'],
            'missing temporary directory' => [UPLOAD_ERR_NO_TMP_DIR, 'The file could not be uploaded.'],
            'write failure' => [UPLOAD_ERR_CANT_WRITE, 'The file could not be uploaded.'],
            'stopped by extension' => [UPLOAD_ERR_EXTENSION, 'The file could not be uploaded.'],
        ];
    }

    private function createUploadedFile(RequestHandlerInterface $requestHandler, $path, $originalName, $errorCode = 0)
    {
        if ($requestHandler instanceof HttpFoundationRequestHandler) {
            $class = new \ReflectionClass(UploadedFile::class);

            if (5 === $class->getConstructor()->getNumberOfParameters()) {
                return new UploadedFile($path, $originalName, null, $errorCode, true);
            }

            return new UploadedFile($path, $originalName, null, null, $errorCode, true);
        }

        return [
            'name' => $originalName,
            'error' => $errorCode,
            'type' => 'text/plain',
            'tmp_name' => $path,
            'size' => null,
        ];
    }
}
