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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractRequestHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Form\RequestHandlerInterface
     */
    protected $requestHandler;

    protected $request;

    protected function setUp()
    {
        $this->requestHandler = $this->getRequestHandler();
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

    abstract protected function setRequestData($method, $data, $files = array());

    abstract protected function getRequestHandler();

    abstract protected function getMockFile();

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
