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

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\RequestHandlerInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractRequestHandlerTest extends \PHPUnit_Framework_TestCase
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
        $this->serverParams = $this->getMock(
            'Symfony\Component\Form\Util\ServerParams',
            array('getNormalizedIniPostMaxSize', 'getContentLength')
        );
        $this->requestHandler = $this->getRequestHandler();
        $this->factory = Forms::createFormFactoryBuilder()->getFormFactory();
        $this->request = null;
    }

    public function methodExceptGetProvider()
    {
        return array(
            array('POST'),
            array('PUT'),
            array('DELETE'),
            array('PATCH'),
        );
    }

    public function methodProvider()
    {
        return array_merge(array(
            array('GET'),
        ), $this->methodExceptGetProvider());
    }

    /**
     * @dataProvider methodProvider
     */
    public function testSubmitIfNameInRequest($method)
    {
        $form = $this->getMockForm('param1', $method);

        $this->setRequestData($method, array(
            'param1' => 'DATA',
        ));

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

        $this->setRequestData($otherMethod, array(
            'param1' => 'DATA',
        ));

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

        $this->setRequestData($method, array(
            'paramx' => array(),
        ));

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

        $this->setRequestData($method, array(
            'paramx' => array(),
        ));

        $form->expects($this->never())
            ->method('submit');

        $this->requestHandler->handleRequest($form, $this->request);
    }

    public function testDoNotSubmitIfNameNotInRequestAndGetRequest()
    {
        $form = $this->getMockForm('param1', 'GET');

        $this->setRequestData('GET', array(
            'paramx' => array(),
        ));

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
            ->will($this->returnValue(array(
                'param1' => $this->getMockForm('param1'),
                'param2' => $this->getMockForm('param2'),
            )));

        $this->setRequestData($method, $requestData = array(
            'param1' => 'submitted value',
            'paramx' => 'submitted value',
        ));

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
            ->will($this->returnValue(array(
                'param1' => $this->getMockForm('param1'),
                'param2' => $this->getMockForm('param2'),
            )));

        $this->setRequestData($method, array(
            'paramx' => 'submitted value',
        ));

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

        $this->setRequestData($method, array(
            'param1' => array(
                'field1' => 'DATA',
            ),
        ), array(
            'param1' => array(
                'field2' => $file,
            ),
        ));

        $form->expects($this->once())
            ->method('submit')
            ->with(array(
                'field1' => 'DATA',
                'field2' => $file,
            ), 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testParamTakesPrecedenceOverFile($method)
    {
        $form = $this->getMockForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, array(
            'param1' => 'DATA',
        ), array(
            'param1' => $file,
        ));

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

        $this->setRequestData($method, array(
            'param1' => null,
        ), array(
            'param1' => $file,
        ));

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

        $this->setRequestData($method, array(
            'param1' => null,
        ), array(
            'param2' => $this->getMockFile('2'),
            'param1' => $file,
            'param3' => $this->getMockFile('3'),
        ));

        $form->expects($this->once())
             ->method('submit')
             ->with($file, 'PATCH' !== $method);

        $this->requestHandler->handleRequest($form, $this->request);
    }

    /**
     * @dataProvider getPostMaxSizeFixtures
     */
    public function testAddFormErrorIfPostMaxSizeExceeded($contentLength, $iniMax, $shouldFail, array $errorParams = array())
    {
        $this->serverParams->expects($this->once())
            ->method('getContentLength')
            ->will($this->returnValue($contentLength));
        $this->serverParams->expects($this->any())
            ->method('getNormalizedIniPostMaxSize')
            ->will($this->returnValue($iniMax));

        $options = array('post_max_size_message' => 'Max {{ max }}!');
        $form = $this->factory->createNamed('name', 'text', null, $options);
        $this->setRequestData('POST', array(), array());

        $this->requestHandler->handleRequest($form, $this->request);

        if ($shouldFail) {
            $errors = array(new FormError($options['post_max_size_message'], null, $errorParams));

            $this->assertEquals($errors, $form->getErrors());
            $this->assertTrue($form->isSubmitted());
        } else {
            $this->assertCount(0, $form->getErrors());
            $this->assertFalse($form->isSubmitted());
        }
    }

    public function getPostMaxSizeFixtures()
    {
        return array(
            array(pow(1024, 3) + 1, '1G', true, array('{{ max }}' => '1G')),
            array(pow(1024, 3), '1G', false),
            array(pow(1024, 2) + 1, '1M', true, array('{{ max }}' => '1M')),
            array(pow(1024, 2), '1M', false),
            array(1024 + 1, '1K', true, array('{{ max }}' => '1K')),
            array(1024, '1K', false),
            array(null, '1K', false),
            array(1024, '', false),
            array(1024, 0, false),
        );
    }

    abstract protected function setRequestData($method, $data, $files = array());

    abstract protected function getRequestHandler();

    abstract protected function getMockFile($suffix = '');

    protected function getMockForm($name, $method = null, $compound = true)
    {
        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');
        $config->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue($method));
        $config->expects($this->any())
            ->method('getCompound')
            ->will($this->returnValue($compound));

        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        $form->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($config));

        return $form;
    }
}
