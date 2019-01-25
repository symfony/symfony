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
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\RequestHandlerInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractRequestHandlerTest extends TestCase
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

    protected function setUp()
    {
        $this->serverParams = $this->getMockBuilder('Symfony\Component\Form\Util\ServerParams')->setMethods(['getNormalizedIniPostMaxSize', 'getContentLength'])->getMock();
        $this->requestHandler = $this->getRequestHandler();
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $this->request = null;
    }

    public function methodExceptGetProvider()
    {
        return [
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
        ];
    }

    public function methodProvider()
    {
        return array_merge([
            ['GET'],
        ], $this->methodExceptGetProvider());
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
        $file = $this->getMockFile();

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

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testParamTakesPrecedenceOverFile($method)
    {
        $form = $this->createForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            'param1' => 'DATA',
        ], [
            'param1' => $file,
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame('DATA', $form->getData());
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitFileIfNoParam($method)
    {
        $form = $this->createBuilder('param1', false, ['allow_file_upload' => true])
            ->setMethod($method)
            ->getForm();
        $file = $this->getMockFile();

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
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            'param1' => null,
        ], [
            'param2' => $this->getMockFile('2'),
            'param1' => $file,
            'param3' => $this->getMockFile('3'),
        ]);

        $this->requestHandler->handleRequest($form, $this->request);

        $this->assertTrue($form->isSubmitted());
        $this->assertSame($file, $form->getData());
    }

    /**
     * @dataProvider getPostMaxSizeFixtures
     */
    public function testAddFormErrorIfPostMaxSizeExceeded($contentLength, $iniMax, $shouldFail, array $errorParams = [])
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue($contentLength));
        $this->serverParams->expects($this->any())
            ->method('getNormalizedIniPostMaxSize')
            ->will($this->returnValue($iniMax));

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

    public function getPostMaxSizeFixtures()
    {
        return [
            [pow(1024, 3) + 1, '1G', true, ['{{ max }}' => '1G']],
            [pow(1024, 3), '1G', false],
            [pow(1024, 2) + 1, '1M', true, ['{{ max }}' => '1M']],
            [pow(1024, 2), '1M', false],
            [1024 + 1, '1K', true, ['{{ max }}' => '1K']],
            [1024, '1K', false],
            [null, '1K', false],
            [1024, '', false],
            [1024, 0, false],
        ];
    }

    public function testUploadedFilesAreAccepted()
    {
        $this->assertTrue($this->requestHandler->isFileUpload($this->getMockFile()));
    }

    public function testInvalidFilesAreRejected()
    {
        $this->assertFalse($this->requestHandler->isFileUpload($this->getInvalidFile()));
    }

    abstract protected function setRequestData($method, $data, $files = []);

    abstract protected function getRequestHandler();

    abstract protected function getMockFile($suffix = '');

    abstract protected function getInvalidFile();

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
        $builder = new FormBuilder($name, null, new EventDispatcher(), $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock(), $options);
        $builder->setCompound($compound);

        if ($compound) {
            $builder->setDataMapper(new PropertyPathMapper());
        }

        return $builder;
    }
}
