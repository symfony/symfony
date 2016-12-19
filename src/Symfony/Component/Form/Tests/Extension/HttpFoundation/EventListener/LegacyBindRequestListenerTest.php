<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\HttpFoundation\EventListener;

use Symfony\Component\Form\Extension\HttpFoundation\EventListener\BindRequestListener;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group legacy
 */
class LegacyBindRequestListenerTest extends \PHPUnit_Framework_TestCase
{
    private $values;

    private $filesPlain;

    private $filesNested;

    /**
     * @var UploadedFile
     */
    private $uploadedFile;

    protected function setUp()
    {
        $path = tempnam(sys_get_temp_dir(), 'sf2');
        touch($path);

        $this->values = array(
            'name' => 'Bernhard',
            'image' => array('filename' => 'foobar.png'),
        );

        $this->filesPlain = array(
            'image' => array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'upload.png',
                'size' => 123,
                'tmp_name' => $path,
                'type' => 'image/png',
            ),
        );

        $this->filesNested = array(
            'error' => array('image' => UPLOAD_ERR_OK),
            'name' => array('image' => 'upload.png'),
            'size' => array('image' => 123),
            'tmp_name' => array('image' => $path),
            'type' => array('image' => 'image/png'),
        );

        $this->uploadedFile = new UploadedFile($path, 'upload.png', 'image/png', 123, UPLOAD_ERR_OK);
    }

    protected function tearDown()
    {
        unlink($this->uploadedFile->getRealPath());
    }

    public function requestMethodProvider()
    {
        return array(
            array('POST'),
            array('PUT'),
            array('DELETE'),
            array('PATCH'),
        );
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitRequest($method)
    {
        $values = array('author' => $this->values);
        $files = array('author' => $this->filesNested);
        $request = new Request(array(), $values, array(), array(), $files, array(
            'REQUEST_METHOD' => $method,
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertEquals(array(
            'name' => 'Bernhard',
            'image' => $this->uploadedFile,
        ), $event->getData());
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitRequestWithEmptyName($method)
    {
        $request = new Request(array(), $this->values, array(), array(), $this->filesPlain, array(
            'REQUEST_METHOD' => $method,
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('', null, $dispatcher);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertEquals(array(
            'name' => 'Bernhard',
            'image' => $this->uploadedFile,
        ), $event->getData());
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitEmptyRequestToCompoundForm($method)
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'REQUEST_METHOD' => $method,
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $config->setCompound(true);
        $config->setDataMapper($this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock());
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        // Default to empty array
        $this->assertEquals(array(), $event->getData());
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testSubmitEmptyRequestToSimpleForm($method)
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'REQUEST_METHOD' => $method,
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $config->setCompound(false);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        // Default to null
        $this->assertNull($event->getData());
    }

    public function testSubmitGetRequest()
    {
        $values = array('author' => $this->values);
        $request = new Request($values, array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertEquals(array(
            'name' => 'Bernhard',
            'image' => array('filename' => 'foobar.png'),
        ), $event->getData());
    }

    public function testSubmitGetRequestWithEmptyName()
    {
        $request = new Request($this->values, array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('', null, $dispatcher);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertEquals(array(
            'name' => 'Bernhard',
            'image' => array('filename' => 'foobar.png'),
        ), $event->getData());
    }

    public function testSubmitEmptyGetRequestToCompoundForm()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $config->setCompound(true);
        $config->setDataMapper($this->getMockBuilder('Symfony\Component\Form\DataMapperInterface')->getMock());
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertEquals(array(), $event->getData());
    }

    public function testSubmitEmptyGetRequestToSimpleForm()
    {
        $request = new Request(array(), array(), array(), array(), array(), array(
            'REQUEST_METHOD' => 'GET',
        ));

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $config = new FormConfigBuilder('author', null, $dispatcher);
        $config->setCompound(false);
        $form = new Form($config);
        $event = new FormEvent($form, $request);

        $listener = new BindRequestListener();
        $listener->preBind($event);

        $this->assertNull($event->getData());
    }
}
