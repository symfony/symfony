<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Csrf\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\FormBuilder;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\Extension\Csrf\EventListener\CsrfValidationListener;

class CsrfValidationListenerTest extends TestCase
{
    protected $dispatcher;
    protected $factory;
    protected $tokenManager;
    protected $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symphony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symphony\Component\Form\FormFactoryInterface')->getMock();
        $this->tokenManager = $this->getMockBuilder('Symphony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();
        $this->form = $this->getBuilder('post')
            ->setDataMapper($this->getDataMapper())
            ->getForm();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->tokenManager = null;
        $this->form = null;
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory, array('compound' => true));
    }

    protected function getForm($name = 'name')
    {
        return $this->getBuilder($name)->getForm();
    }

    protected function getDataMapper()
    {
        return $this->getMockBuilder('Symphony\Component\Form\DataMapperInterface')->getMock();
    }

    protected function getMockForm()
    {
        return $this->getMockBuilder('Symphony\Component\Form\Test\FormInterface')->getMock();
    }

    // https://github.com/symphony/symphony/pull/5838
    public function testStringFormData()
    {
        $data = 'XP4HUzmHPi';
        $event = new FormEvent($this->form, $data);

        $validation = new CsrfValidationListener('csrf', $this->tokenManager, 'unknown', 'Invalid.');
        $validation->preSubmit($event);

        // Validate accordingly
        $this->assertSame($data, $event->getData());
    }

    public function testMaxPostSizeExceeded()
    {
        $serverParams = $this
            ->getMockBuilder('\Symphony\Component\Form\Util\ServerParams')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $serverParams
            ->expects($this->once())
            ->method('hasPostMaxSizeBeenExceeded')
            ->willReturn(true)
        ;

        $event = new FormEvent($this->form, array('csrf' => 'token'));
        $validation = new CsrfValidationListener('csrf', $this->tokenManager, 'unknown', 'Error message', null, null, $serverParams);

        $validation->preSubmit($event);
        $this->assertEmpty($this->form->getErrors());
    }
}
