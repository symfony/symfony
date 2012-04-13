<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Validator;

use Symfony\Component\Form\Extension\Core\EventListener\ValidationListener;

class DefaultValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testThatAddErrorWhenNotSynchornized()
    {
        $form = $this->getFormMock();
        $form->expects($this->once())
            ->method('isSynchronized')
            ->will($this->returnValue(false));
        $form->expects($this->at(1))
            ->method('getAttribute')
            ->will($this->returnValue('message'));
        $form->expects($this->at(2))
            ->method('getAttribute')
            ->will($this->returnValue(array()));
        $form->expects($this->once())
            ->method('addError');

        $validator = new ValidationListener();
        $validator->validateForm($this->getDataEventMock($form));
    }

    public function testThatAddErrorWhenHaveExtraFields()
    {
        $form = $this->getFormMock();
        $form->expects($this->once())
            ->method('isSynchronized')
            ->will($this->returnValue(true));
        $form->expects($this->once())
            ->method('getExtraData')
            ->will($this->returnValue(array('extra_data_field' => 1)));
        $form->expects($this->once())
            ->method('addError');


        $validator = new ValidationListener();
        $validator->validateForm($this->getDataEventMock($form));
    }

    public function testThatAddErrorWhenContentSizeIsTooLarge()
    {
        $form = $this->getFormMock();
        $form->expects($this->once())
            ->method('isSynchronized')
            ->will($this->returnValue(true));
        $form->expects($this->once())
            ->method('isRoot')
            ->will($this->returnValue(true));
        $form->expects($this->once())
            ->method('addError');

        $_SERVER['CONTENT_LENGTH'] = 1073741825;

        $validator = $this->getMockBuilder('Symfony\Component\Form\Extension\Core\EventListener\ValidationListener')
            ->setMethods(array('getPostMaxSize'))
            ->getMock();
        $validator->expects($this->once())
            ->method('getPostMaxSize')
            ->will($this->returnValue('1G'));

        $validator->validateForm($this->getDataEventMock($form));
    }

    private function getFormMock()
    {
        return $this->getMock('Symfony\Component\Form\Tests\FormInterface');
    }

    private function getDataEventMock($form)
    {
        $dataEvent = $this->getMockBuilder('Symfony\Component\Form\Event\DataEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $dataEvent->expects($this->any())
            ->method('getForm')
            ->will($this->returnValue($form));

        return $dataEvent;
    }
}
