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
        $form = $this->getMockForm('param1', $method);

        $this->setRequestData($method, [
            'param1' => 'DATA',
        ]);

        $form->expects($this->once())
            ->method('submit')
            ->with('DATA', 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotSubmitIfWrongRequestMethod($method)
    {
        $form = $this->getMockForm('param1', $method);

        $otherMethod = 'POST' === $method ? 'PUT' : 'POST';

        $this->setRequestData($otherMethod, [
            'param1' => 'DATA',
        ]);

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testDoNoSubmitSimpleFormIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->getMockForm('param1', $method, false);

        $this->setRequestData($method, [
            'paramx' => [],
        ]);

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testDoNotSubmitCompoundFormIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->getMockForm('param1', $method, true);

        $this->setRequestData($method, [
            'paramx' => [],
        ]);

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testDoNotSubmitIfNameNotInRequestAndGetRequest()
    {
        $form = $this->getMockForm('param1', 'GET');

        $this->setRequestData('GET', [
            'paramx' => [],
        ]);

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testSubmitFormWithEmptyNameIfAtLeastOneFieldInRequest($method)
    {
        $form = $this->getMockForm('', $method);
        $form->expects($this->any())
            ->method('all')
            ->will($this->returnValue([
                'param1' => $this->getMockForm('param1'),
                'param2' => $this->getMockForm('param2'),
            ]));

        $this->setRequestData($method, $requestData = [
            'param1' => 'submitted value',
            'paramx' => 'submitted value',
        ]);

        $form->expects($this->once())
            ->method('submit')
            ->with($requestData, 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotSubmitFormWithEmptyNameIfNoFieldInRequest($method)
    {
        $form = $this->getMockForm('', $method);
        $form->expects($this->any())
            ->method('all')
            ->will($this->returnValue([
                'param1' => $this->getMockForm('param1'),
                'param2' => $this->getMockForm('param2'),
            ]));

        $this->setRequestData($method, [
            'paramx' => 'submitted value',
        ]);

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testMergeParamsAndFiles($method)
    {
        $form = $this->getMockForm('param1', $method);
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

        $form->expects($this->once())
            ->method('submit')
            ->with([
                'field1' => 'DATA',
                'field2' => $file,
            ], 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testParamTakesPrecedenceOverFile($method)
    {
        $form = $this->getMockForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            'param1' => 'DATA',
        ], [
            'param1' => $file,
        ]);

        $form->expects($this->once())
            ->method('submit')
            ->with('DATA', 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitFileIfNoParam($method)
    {
        $form = $this->getMockForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            'param1' => null,
        ], [
            'param1' => $file,
        ]);

        $form->expects($this->once())
            ->method('submit')
            ->with($file, 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitMultipleFiles($method)
    {
        $form = $this->getMockForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            'param1' => null,
        ], [
            'param2' => $this->getMockFile('2'),
            'param1' => $file,
            'param3' => $this->getMockFile('3'),
        ]);

        $form->expects($this->once())
             ->method('submit')
             ->with($file, 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testSubmitFileWithNamelessForm($method)
    {
        $form = $this->getMockForm(null, $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, [
            '' => null,
        ], [
            '' => $file,
        ]);

        $form->expects($this->once())
             ->method('submit')
             ->with($file, 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
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

    protected function getMockForm($name, $method = null, $compound = true)
    {
        $config = $this->getMockBuilder('Symfony\Component\Form\FormConfigInterface')->getMock();
        $config->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue($method));
        $config->expects($this->any())
            ->method('getCompound')
            ->will($this->returnValue($compound));

        $form = $this->getMockBuilder('Symfony\Component\Form\Test\FormInterface')->getMock();
        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        $form->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($config));

        return $form;
    }
}
