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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\Tests\Extension\Type\ItemFileType;
use Symfony\Component\Form\Util\ServerParams;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractRequestHandlerTestCase extends TestCase
{
    /**
     * @var RequestHandlerInterface
     */
    protected $requestHandler;

    /**
     * @var FormFactory
     */
    protected $factory;

    protected $request;

    protected $serverParams;

    protected function setUp(): void
    {
        $this->serverParams = new class() extends ServerParams {
            public $contentLength;
            public $postMaxSize = '';

            public function getContentLength(): ?int
            {
                return $this->contentLength;
            }

            public function getNormalizedIniPostMaxSize(): string
            {
                return $this->postMaxSize;
            }
        };

        $this->requestHandler = $this->getRequestHandler();
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $this->request = null;
    }

    public static function methodExceptGetProvider(): array
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
        ];
    }

    public static function methodProvider(): array
    {
        return array_merge([
            ['GET'],
        ], self::methodExceptGetProvider());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testSubmitIfNameInRequest($method)
    {
        $form = $this->createForm('param1', $method);

        $this->setRequestData($method, [
            'param1' => 'DATA',
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('DATA', $form->getData());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotSubmitIfWrongRequestMethod($method)
    {
        $form = $this->createForm('param1', $method);

        $otherMethod = 'POST' === $method ? 'PUT' : 'POST';

        $this->setRequestData($otherMethod, [
            'param1' => 'DATA',
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testDoNoSubmitSimpleFormIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->createForm('param1', $method, false);

        $this->setRequestData($method, [
            'paramx' => [],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testDoNotSubmitCompoundFormIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->createForm('param1', $method, true);

        $this->setRequestData($method, [
            'paramx' => [],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    public function testDoNotSubmitIfNameNotInRequestAndGetRequest()
    {
        $form = $this->createForm('param1', 'GET');

        $this->setRequestData('GET', [
            'paramx' => [],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testSubmitFormWithEmptyNameIfAtLeastOneFieldInRequest($method)
    {
        $form = $this->createForm('', $method, true);
        $form->add($this->createForm('param1'));
        $form->add($this->createForm('param2'));

        $this->setRequestData($method, $requestData = [
            'param1' => 'submitted value',
            'paramx' => 'submitted value',
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->get('param1')->isSubmitted());
        $this->assertSame('submitted value', $form->get('param1')->getData());

        if ('PATCH' === $method) {
            $this->assertFalse($form->get('param2')->isSubmitted());
        } else {
            $this->assertTrue($form->get('param2')->isSubmitted());
        }

        $this->assertNull($form->get('param2')->getData());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotSubmitFormWithEmptyNameIfNoFieldInRequest($method)
    {
        $form = $this->createForm('', $method, true);
        $form->add($this->createForm('param1'));
        $form->add($this->createForm('param2'));

        $this->setRequestData($method, [
            'paramx' => 'submitted value',
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertFalse($form->isSubmitted());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testMergeParamsAndFiles($method)
    {
        $form = $this->createForm('param1', $method, true);
        $form->add($this->createForm('field1'));
        $form->add($this->createBuilder('field2', false, ['allow_file_upload' => true])->getForm());
        $file = $this->getUploadedFile();

        $this->setRequestData($method, [
            'param1' => [
                'field1' => 'DATA',
            ],
        ], [
            'param1' => [
                'field2' => $file,
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('DATA', $form->get('field1')->getData());
        $this->assertSame($file, $form->get('field2')->getData());
    }

    public function testIntegerChildren()
    {
        $form = $this->createForm('root', 'POST', true);
        $form->add('0', TextType::class);
        $form->add('1', TextType::class);

        $this->setRequestData('POST', [
            'root' => [
                '1' => 'bar',
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertNull($form->get('0')->getData());
        $this->assertSame('bar', $form->get('1')->getData());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testMergeParamsAndFilesMultiple($method)
    {
        $form = $this->createForm('param1', $method, true);
        $form->add($this->createBuilder('field1', false, ['allow_file_upload' => true, 'multiple' => true])->getForm());
        $file1 = $this->getUploadedFile();
        $file2 = $this->getUploadedFile();

        $this->setRequestData($method, [
            'param1' => [
                'field1' => [
                    'foo',
                    'bar',
                    'baz',
                ],
            ],
        ], [
            'param1' => [
                'field1' => [
                    $file1,
                    $file2,
                ],
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);
        $data = $form->get('field1')->getData();

        $this->assertTrue($form->isSubmitted());
        $this->assertIsArray($data);
        $this->assertCount(5, $data);
        $this->assertSame(['foo', 'bar', 'baz', $file1, $file2], $data);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testParamTakesPrecedenceOverFile($method)
    {
        $form = $this->createForm('param1', $method);
        $file = $this->getUploadedFile();

        $this->setRequestData($method, [
            'param1' => 'DATA',
        ], [
            'param1' => $file,
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('DATA', $form->getData());
    }

    public function testMergeZeroIndexedCollection()
    {
        $form = $this->createForm('root', 'POST', true);
        $form->add('items', CollectionType::class, [
            'entry_type' => ItemFileType::class,
            'allow_add' => true,
        ]);

        $file = $this->getUploadedFile();

        $this->setRequestData('POST', [
            'root' => [
                'items' => [
                    0 => [
                        'item' => 'test',
                    ],
                ],
            ],
        ], [
            'root' => [
                'items' => [
                    0 => [
                        'file' => $file,
                    ],
                ],
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $itemsForm = $form->get('items');

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());

        $this->assertTrue($itemsForm->has('0'));
        $this->assertFalse($itemsForm->has('1'));

        $this->assertEquals('test', $itemsForm->get('0')->get('item')->getData());
        $this->assertNotNull($itemsForm->get('0')->get('file'));
    }

    public function testMergePartialDataFromCollection()
    {
        $form = $this->createForm('root', 'POST', true);
        $form->add('items', CollectionType::class, [
            'entry_type' => ItemFileType::class,
            'allow_add' => true,
        ]);

        $file = $this->getUploadedFile();
        $file2 = $this->getUploadedFile();

        $this->setRequestData('POST', [
            'root' => [
                'items' => [
                    1 => [
                        'item' => 'test',
                    ],
                ],
            ],
        ], [
            'root' => [
                'items' => [
                    0 => [
                        'file' => $file,
                    ],
                    1 => [
                        'file' => $file2,
                    ],
                ],
            ],
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $itemsForm = $form->get('items');
        $data = $itemsForm->getData();
        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());

        $this->assertCount(2, $data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey(1, $data);

        $this->assertEquals('test', $itemsForm->get('1')->get('item')->getData());
        $this->assertNotNull($itemsForm->get('0')->get('file'));
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitFileIfNoParam($method)
    {
        $form = $this->createBuilder('param1', false, ['allow_file_upload' => true])
            ->setMethod($method)
            ->getForm();
        $file = $this->getUploadedFile();

        $this->setRequestData($method, [
            'param1' => null,
        ], [
            'param1' => $file,
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame($file, $form->getData());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitMultipleFiles($method)
    {
        $form = $this->createBuilder('param1', false, ['allow_file_upload' => true])
            ->setMethod($method)
            ->getForm();
        $file = $this->getUploadedFile();

        $this->setRequestData($method, [
            'param1' => null,
        ], [
            'param2' => $this->getUploadedFile('2'),
            'param1' => $file,
            'param3' => $this->getUploadedFile('3'),
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame($file, $form->getData());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitFileWithNamelessForm($method)
    {
        $form = $this->createForm('', $method, true);
        $fileForm = $this->createBuilder('document', false, ['allow_file_upload' => true])->getForm();
        $form->add($fileForm);
        $file = $this->getUploadedFile();
        $this->setRequestData($method, [
            'document' => null,
        ], [
            'document' => $file,
        ]);
        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame($file, $fileForm->getData());
    }

    /**
     * @dataProvider getPostMaxSizeFixtures
     */
    public function testAddFormErrorIfPostMaxSizeExceeded(?int $contentLength, string $iniMax, bool $shouldFail, array $errorParams = [])
    {
        $this->serverParams->contentLength = $contentLength;
        $this->serverParams->postMaxSize = $iniMax;

        $options = ['post_max_size_message' => 'Max {{ max }}!'];
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, $options);
        $this->setRequestData('POST', [], []);

        $this->requestHandler->handleRequest($form, $this->request);

        if ($shouldFail) {
            $error = new FormError($options['post_max_size_message'], null, $errorParams);
            $error->setOrigin($form);

            $this->assertEquals([$error], iterator_to_array($form->getErrors()));
            $this->assertTrue($form->isSubmitted());
        } else {
            $this->assertCount(0, $form->getErrors());
            $this->assertFalse($form->isSubmitted());
        }
    }

    public static function getPostMaxSizeFixtures()
    {
        return [
            [1024 ** 3 + 1, '1G', true, ['{{ max }}' => '1G']],
            [1024 ** 3, '1G', false],
            [1024 ** 2 + 1, '1M', true, ['{{ max }}' => '1M']],
            [1024 ** 2, '1M', false],
            [1024 + 1, '1K', true, ['{{ max }}' => '1K']],
            [1024, '1K', false],
            [null, '1K', false],
            [1024, '', false],
            [1024, '0', false],
        ];
    }

    public function testUploadedFilesAreAccepted()
    {
        $this->assertTrue($this->requestHandler->isFileUpload($this->getUploadedFile()));
    }

    public function testInvalidFilesAreRejected()
    {
        $this->assertFalse($this->requestHandler->isFileUpload($this->getInvalidFile()));
    }

    /**
     * @dataProvider uploadFileErrorCodes
     */
    public function testFailedFileUploadIsTurnedIntoFormError($errorCode, $expectedErrorCode)
    {
        $this->assertSame($expectedErrorCode, $this->requestHandler->getUploadFileError($this->getFailedUploadedFile($errorCode)));
    }

    public static function uploadFileErrorCodes()
    {
        return [
            'no error' => [\UPLOAD_ERR_OK, null],
            'upload_max_filesize ini directive' => [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_INI_SIZE],
            'MAX_FILE_SIZE from form' => [\UPLOAD_ERR_FORM_SIZE, \UPLOAD_ERR_FORM_SIZE],
            'partially uploaded' => [\UPLOAD_ERR_PARTIAL, \UPLOAD_ERR_PARTIAL],
            'no file upload' => [\UPLOAD_ERR_NO_FILE, \UPLOAD_ERR_NO_FILE],
            'missing temporary directory' => [\UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_NO_TMP_DIR],
            'write failure' => [\UPLOAD_ERR_CANT_WRITE, \UPLOAD_ERR_CANT_WRITE],
            'stopped by extension' => [\UPLOAD_ERR_EXTENSION, \UPLOAD_ERR_EXTENSION],
        ];
    }

    abstract protected function setRequestData($method, $data, $files = []);

    abstract protected function getRequestHandler();

    abstract protected function getUploadedFile($suffix = '');

    abstract protected function getInvalidFile();

    abstract protected function getFailedUploadedFile($errorCode);

    protected function createForm($name, $method = null, $compound = false)
    {
        $config = $this->createBuilder($name, $compound);

        if (null !== $method) {
            $config->setMethod($method);
        }

        return new Form($config);
    }

    protected function createBuilder($name, $compound = false, array $options = [])
    {
        $builder = new FormBuilder($name, null, new EventDispatcher(), new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory())), $options);
        $builder->setCompound($compound);

        if ($compound) {
            $builder->setDataMapper(new DataMapper());
        }

        return $builder;
    }
}
