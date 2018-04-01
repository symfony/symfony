<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\Form\FormBuilder;
use Symphony\Component\Form\FormError;
use Symphony\Component\Form\FormErrorIterator;
use Symphony\Component\Validator\ConstraintViolation;

class FormErrorIteratorTest extends TestCase
{
    /**
     * @dataProvider findByCodesProvider
     */
    public function testFindByCodes($code, $violationsCount)
    {
        if (!class_exists(ConstraintViolation::class)) {
            $this->markTestSkipped('Validator component required.');
        }

        $formBuilder = new FormBuilder(
            'form',
            null,
            new EventDispatcher(),
            $this->getMockBuilder('Symphony\Component\Form\FormFactoryInterface')->getMock(),
            array()
        );

        $form = $formBuilder->getForm();

        $cause = new ConstraintViolation('Error 1!', null, array(), null, '', null, null, 'code1');
        $form->addError(new FormError('Error 1!', null, array(), null, $cause));
        $cause = new ConstraintViolation('Error 2!', null, array(), null, '', null, null, 'code1');
        $form->addError(new FormError('Error 2!', null, array(), null, $cause));
        $cause = new ConstraintViolation('Error 3!', null, array(), null, '', null, null, 'code2');
        $form->addError(new FormError('Error 3!', null, array(), null, $cause));
        $formErrors = $form->getErrors();

        $specificFormErrors = $formErrors->findByCodes($code);
        $this->assertInstanceOf(FormErrorIterator::class, $specificFormErrors);
        $this->assertCount($violationsCount, $specificFormErrors);
    }

    public function findByCodesProvider()
    {
        return array(
            array('code1', 2),
            array(array('code1', 'code2'), 3),
            array('code3', 0),
        );
    }
}
