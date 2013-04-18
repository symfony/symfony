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
abstract class AbstractFormProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Form\FormProcessorInterface
     */
    protected $processor;

    protected $request;

    protected function setUp()
    {
        $this->processor = $this->getFormProcessor();
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
    public function testBindIfNameInRequest($method)
    {
        $form = $this->getMockForm('param1', $method);

        $this->setRequestData($method, array(
            'param1' => 'DATA',
        ));

        $form->expects($this->once())
            ->method('bind')
            ->with('DATA');

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotBindIfWrongRequestMethod($method)
    {
        $form = $this->getMockForm('param1', $method);

        $otherMethod = 'POST' === $method ? 'PUT' : 'POST';

        $this->setRequestData($otherMethod, array(
            'param1' => 'DATA',
        ));

        $form->expects($this->never())
            ->method('bind');

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testBindSimpleFormWithNullIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->getMockForm('param1', $method, false);

        $this->setRequestData($method, array(
            'paramx' => array(),
        ));

        $form->expects($this->once())
            ->method('bind')
            ->with($this->identicalTo(null));

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testBindCompoundFormWithArrayIfNameNotInRequestAndNotGetRequest($method)
    {
        $form = $this->getMockForm('param1', $method, true);

        $this->setRequestData($method, array(
            'paramx' => array(),
        ));

        $form->expects($this->once())
            ->method('bind')
            ->with($this->identicalTo(array()));

        $this->processor->processForm($form, $this->request);
    }

    public function testDoNotBindIfNameNotInRequestAndGetRequest()
    {
        $form = $this->getMockForm('param1', 'GET');

        $this->setRequestData('GET', array(
            'paramx' => array(),
        ));

        $form->expects($this->never())
            ->method('bind');

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testBindFormWithEmptyNameIfAtLeastOneFieldInRequest($method)
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
            ->method('bind')
            ->with($requestData);

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testDoNotBindFormWithEmptyNameIfNoFieldInRequest($method)
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
            ->method('bind');

        $this->processor->processForm($form, $this->request);
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
            ->method('bind')
            ->with(array(
                'field1' => 'DATA',
                'field2' => $file,
            ));

        $this->processor->processForm($form, $this->request);
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
            ->method('bind')
            ->with('DATA');

        $this->processor->processForm($form, $this->request);
    }

    /**
     * @dataProvider methodExceptGetProvider
     */
    public function testBindFileIfNoParam($method)
    {
        $form = $this->getMockForm('param1', $method);
        $file = $this->getMockFile();

        $this->setRequestData($method, array(
            'param1' => null,
        ), array(
            'param1' => $file,
        ));

        $form->expects($this->once())
            ->method('bind')
            ->with($file);

        $this->processor->processForm($form, $this->request);
    }

    abstract protected function setRequestData($method, $data, $files = array());

    abstract protected function getFormProcessor();

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
