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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Validator\ConstraintViolation;

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
            $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock(),
            []
        );

        $form = $formBuilder->getForm();

        $cause = new ConstraintViolation('Error 1!', null, [], null, '', null, null, 'code1');
        $form->addError(new FormError('Error 1!', null, [], null, $cause));
        $cause = new ConstraintViolation('Error 2!', null, [], null, '', null, null, 'code1');
        $form->addError(new FormError('Error 2!', null, [], null, $cause));
        $cause = new ConstraintViolation('Error 3!', null, [], null, '', null, null, 'code2');
        $form->addError(new FormError('Error 3!', null, [], null, $cause));
        $formErrors = $form->getErrors();

        $specificFormErrors = $formErrors->findByCodes($code);
        $this->assertInstanceOf(FormErrorIterator::class, $specificFormErrors);
        $this->assertCount($violationsCount, $specificFormErrors);
    }

    public function findByCodesProvider()
    {
        return [
            ['code1', 2],
            [['code1', 'code2'], 3],
            ['code3', 0],
        ];
    }
}
